<?php

namespace addons\shopro\library\traits;

use addons\shopro\exception\Exception;
use addons\shopro\model\Activity;
use addons\shopro\model\ActivityGroupon;
use addons\shopro\model\ActivityGrouponLog;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use addons\shopro\model\UserFake;

/**
 * 拼团
 */
trait Groupon
{
    /**
     * *、redis 没有存团完整信息，只存了团当前人数，团成员（当前人数，团成员均没有存虚拟用户）
     * *、redis userList 没有存这个人的购买状态
     * *、团 解散，成团，虚拟成团，没有修改 redis 团信息（因为直接修改了数据库，参团判断，先判断的数据库后判断的 redis）
     */



    /**
     * 增加拼团预成员人数
     */
    protected function grouponCacheForwardNum($activityGroupon, $activity, $user, $payed = 'nopay')
    {
        if (!$this->hasRedis()) {
            return true;
        }

        $keys = $this->getKeys([
            'groupon_id' => $activityGroupon['id'],
            'goods_id' => $activityGroupon['goods_id'],
        ], [
            'activity_id' => $activity['id'],
            'activity_type' => $activity['type'],
        ]);

        extract($keys);

        $redis = $this->getRedis();

        // 将拼团团信息存入 redis 没有用，还得维护团状态，先不存
        // $redis->HSET($activityHashKey, $grouponKey, json_encode($activityGroupon));

        // 当前团人数 grouponNumKey 如果不存在，自动创建
        $current_num = $redis->HINCRBY($activityHashKey, $grouponNumKey, 1);

        if ($current_num > $activityGroupon['num']) {
            // 再把刚加上的减回来
            $current_num = $redis->HINCRBY($activityHashKey, $grouponNumKey, -1);

            new Exception('该团已满，请参与其它团或自己开团');
        }

        // 将用户加入拼团缓存，用来判断同一个人在一个团，多次下单，取消订单删除
        $userList = $redis->HGET($activityHashKey, $grouponUserlistKey);
        $userList = json_decode($userList, true);
        $userList = $userList ? : [];
        $userList[] = [
            'user_id' => $user['id'],
            // 'status' => $payed       // 太复杂，先不做
        ];
        $redis->HSET($activityHashKey, $grouponUserlistKey, json_encode($userList));
    }



    // 拼团团成员预成员退回
    protected function grouponCacheBackNum($order)
    {
        $items = OrderItem::where('order_id', $order['id'])->select();

        foreach ($items as $key => $item) {
            // 不是拼团，或者 没有配置 redis
            if (strpos($item['activity_type'], 'groupon') === false || !$this->hasRedis()) {
                continue;
            }

            // 扩展字段
            $order_ext = $order['ext_arr'];
            // 团 id
            $groupon_id = $order_ext['groupon_id'] ?? 0;

            if (!$groupon_id) {
                continue;       // 商品独立购买，未参团,或者开新团
            }

            // 实例化 redis
            $redis = $this->getRedis();

            $keys = $this->getKeys([
                'groupon_id' => $groupon_id,
                'goods_id' => $item['goods_id'],
                'goods_sku_price_id' => $item['goods_sku_price_id'],
            ], [
                'activity_id' => $item['activity_id'],
                'activity_type' => $item['activity_type'],
            ]);

            extract($keys);

            // 扣除预参团成员
            if ($redis->EXISTS($activityHashKey) && $redis->HEXISTS($activityHashKey, $grouponNumKey)) {
                $sale = $redis->HINCRBY($activityHashKey, $grouponNumKey, -1);
            }

            $userList = $redis->HGET($activityHashKey, $grouponUserlistKey);
            $userList = json_decode($userList, true);
            $userList = $userList ?: [];
            foreach($userList as $key => $user) {
                if ($user['user_id'] == $item['user_id']) {
                    unset($userList[$key]);
                }
            }
            $redis->HSET($activityHashKey, $grouponUserlistKey, json_encode($userList));
        }
    }


    /**
     * 判断加入旧拼团
     */
    protected function checkJoinGroupon($goods_info, $user, $groupon_id)
    {
        $goods = $goods_info['detail'];
        $activity = $goods['activity'];
        $rules = $activity['rules'];

        // 获取团信息
        $activityGroupon = ActivityGroupon::with('activity')->where('id', $groupon_id)->find();
        if (!$activityGroupon) {
            new Exception('要参与的团不存在');
        }
        // 判断团所属活动是否正常
        if (!$activityGroupon->activity || $activityGroupon->activity['id'] != $activity['id']) {      // 修复，后台手动将活动删除，然后又立即给这个商品创建新的拼团活动，导致参与新活动的旧团错乱问题
            new Exception('要参与的活动已结束');
        }
        if ($activityGroupon['status'] != 'ing') {
            new Exception('要参与的团已成团，请选择其它团或自己开团');
        }

        if ($activityGroupon['current_num'] >= $activityGroupon['num']) {
            new Exception('该团已满，请参与其它团或自己开团');
        }
        
        if (!$this->hasRedis()) {
            // 没有 redis 直接判断数据库团信息，因为 current_num 支付成功才会累加，故无法保证超员，
            // 该团可加入
            return $activityGroupon;
        }

        $keys = $this->getKeys([
            'groupon_id' => $activityGroupon['id'],
            'goods_id' => $activityGroupon['goods_id'],
        ], [
            'activity_id' => $activity['id'],
            'activity_type' => $activity['type'],
        ]);

        extract($keys);

        $redis = $this->getRedis();

        $current_num = $redis->HGET($activityHashKey, $grouponNumKey);
        if ($current_num >= $activityGroupon['num']) {
            new Exception('该团已满，请参与其它团或自己开团');
        }

        // 将用户加入拼团缓存，用来判断同一个人在一个团，多次下单，订单失效删除
        $userList = $redis->HGET($activityHashKey, $grouponUserlistKey);
        $userList = json_decode($userList, true);
        $userIds = array_column($userList, 'user_id');
        if (in_array($user['id'], $userIds)) {
            new Exception('您已参与该团，请不要重复参团');
        }

        return $activityGroupon;
    }



    /**
     * 支付成功真实加入团
     */
    protected function joinGroupon($order, $user) {
        $item = $order->item;
        $goods_item = $item[0];      // 拼团只能单独购买

        // 扩展字段
        $order_ext = $order['ext_arr'];
        // 团 id
        $groupon_id = $order_ext['groupon_id'] ?? 0;
        $buy_type = $order_ext['buy_type'] ?? 0;

        // 不是拼团购买，比如拼团单独购买
        if ($buy_type != 'groupon') {
            return true;
        }

        if ($groupon_id) {
            // 加入旧团，查询团
            $activityGroupon = ActivityGroupon::where('id', $groupon_id)->find();
        } else {
            // 加入新团，创建团
            $activityGroupon = $this->joinNewGroupon($order, $user);
        }
        // 添加参团记录
        $activityGrouponLog = $this->addGrouponLog($order, $user, $activityGroupon);

        return $this->checkGrouponStatus($activityGroupon);
    }


    /**
     * 支付成功开启新拼团
     */
    protected function joinNewGroupon($order, $user)
    {
        $item = $order->item;
        $goodsItem = $item[0];      // 拼团只能单独购买

        // 获取活动
        $activity = Activity::where('id', $goodsItem['activity_id'])->find();
        $rules = $activity['rules'];

        // 小于 0 不限结束时间单位小时
        $expiretime = 0;
        if (isset($rules['valid_time']) && $rules['valid_time'] > 0) {
            // 转为 秒
            $expiretime = $rules['valid_time'] * 3600;
        }

        // 开团
        $activityGroupon = new ActivityGroupon();
        $activityGroupon->user_id = $user['id'];
        $activityGroupon->goods_id = $goodsItem['goods_id'];
        $activityGroupon->activity_id = $goodsItem['activity_id'];
        $activityGroupon->num = $rules['team_num'] ?? 1;        // 避免活动找不到
        $activityGroupon->current_num = 0;              // 真实团成员等支付完成之后再增加
        $activityGroupon->status = 'ing';
        $activityGroupon->expiretime = $expiretime > 0 ? (time() + $expiretime) : 0;
        $activityGroupon->save();

        // 记录团 id
        $order->ext = json_encode($order->setExt($order, ['groupon_id' => $activityGroupon->id]));      // 团 id
        $order->save();

        // 将团信息存入缓存，增加缓存中当前团人数
        $this->grouponCacheForwardNum($activityGroupon, $activity, $user, 'payed');

        if ($expiretime > 0) {
            // 增加自动关闭拼团队列(如果有虚拟成团，会判断虚拟成团)
            \think\Queue::later($expiretime, '\addons\shopro\job\ActivityGrouponAutoOper@expire', [
                'activity' => $activity,
                'activity_groupon_id' => $activityGroupon->id
            ], 'shopro');
        }

        return $activityGroupon;
    }


    /**
     * 增加团成员记录
     */
    protected function addGrouponLog($order, $user, $activityGroupon) {
        if (!$activityGroupon) {
            \think\Log::write('groupon-notfund: order_id: ' . $order['id']);
            return null;
        }

        $item = $order->item;
        $goodsItem = $item[0];      // 拼团只能单独购买

        // 增加团成员数量
        $activityGroupon->setInc('current_num', 1);

        // 增加参团记录
        $activityGrouponLog = new ActivityGrouponLog();
        $activityGrouponLog->user_id = $user['id'];
        $activityGrouponLog->user_nickname = $user['nickname'];
        $activityGrouponLog->user_avatar = $user['avatar'];
        $activityGrouponLog->groupon_id = $activityGroupon['id'] ?? 0;
        $activityGrouponLog->goods_id = $goodsItem['goods_id'];
        $activityGrouponLog->goods_sku_price_id = $goodsItem['goods_sku_price_id'];
        $activityGrouponLog->activity_id = $goodsItem['activity_id'];
        $activityGrouponLog->is_leader = ($activityGroupon['user_id'] == $user['id']) ? 1 : 0;
        $activityGrouponLog->is_fictitious = 0;
        $activityGrouponLog->order_id = $order['id'];
        $activityGrouponLog->save();

        return $activityGrouponLog;
    }


    // 虚拟成团，增加虚拟成员，并判断是否完成，然后将团状态改为，虚拟成团成功
    protected function finishFictitiousGroupon($activityGroupon, $num = 0, $users = []) {
        // 拼团剩余人数
        $surplus_num = $activityGroupon['num'] - $activityGroupon['current_num'];
        
        // 团已经满员
        if ($surplus_num <= 0) {
            if ($activityGroupon['status'] == 'ing') {
                // 已满员但还是进行中状态，检测并完成团，起到纠正作用
                return $this->checkGrouponStatus($activityGroupon);
            }
            return true;
        }

        // 本次虚拟人数, 如果传入 num 则使用 num 和 surplus_num 中最小值， 如果没有传入，默认剩余人数全部虚拟
        $fictitious_num = $num ? ($num > $surplus_num ? $surplus_num : $num) : $surplus_num;

        // 查询虚拟用户
        $userFakes = UserFake::orderRaw('rand()')->limit($fictitious_num)->select();

        if (count($userFakes) < $fictitious_num && $num == 0) {
            // 虚拟用户不足，并且是自动虚拟成团进程，自动解散团
            return $this->invalidRefundGroupon($activityGroupon);
        }

        // 增加团人数
        $activityGroupon->setInc('current_num', $fictitious_num);
        
        for ($i = 0; $i < $fictitious_num; $i ++) {
            // 先用传过来的
            $avatar = isset($users[$i]['avatar']) ? $users[$i]['avatar'] : '';
            $nickname = isset($users[$i]['nickname']) ? $users[$i]['nickname'] : '';

            // 如果没有，用查的虚拟的
            $avatar = $avatar ? : $userFakes[$i]['avatar'];
            $nickname = $nickname ? : $userFakes[$i]['nickname'];

            // 增加参团记录
            $activityGrouponLog = new ActivityGrouponLog();
            $activityGrouponLog->user_id = 0;
            $activityGrouponLog->user_nickname = $nickname;
            $activityGrouponLog->user_avatar = $avatar;
            $activityGrouponLog->groupon_id = $activityGroupon['id'] ?? 0;
            $activityGrouponLog->goods_id = $activityGroupon['goods_id'];
            $activityGrouponLog->goods_sku_price_id = 0;        // 没有订单，所以也就没有 goods_sku_price_id
            $activityGrouponLog->activity_id = $activityGroupon['activity_id'];
            $activityGrouponLog->is_leader = 0;     // 不是团长
            $activityGrouponLog->is_fictitious = 1; // 虚拟用户
            $activityGrouponLog->order_id = 0;      // 虚拟成员没有订单
            $activityGrouponLog->save();
        }

        return $this->checkGrouponStatus($activityGroupon);
    }


    /**
     * 团过期退款，或者后台手动解散退款
     */
    protected function invalidRefundGroupon($activityGroupon, $user = null) {
        $activityGroupon->status = 'invalid';       // 拼团失败
        $activityGroupon->save();

        // 查询参团真人
        $logs = ActivityGrouponLog::with('order')->where('groupon_id', $activityGroupon['id'])->where('is_fictitious', 0)->select();

        foreach ($logs as $key => $log) {
            $order = $log['order'];
            if ($order && $order['status'] > 0) {
                // 退款，只能有一个 item
                $item = $order['item'][0];
                if ($item && in_array($item['refund_status'], [OrderItem::REFUND_STATUS_NOREFUND, OrderItem::REFUND_STATUS_ING])) {
                    // 未申请退款，或者退款中，直接全额退款
                    Order::startRefund($order, $order['item'][0], $order['pay_fee'], $user, '拼团失败退款');
                }
            }

            // 修改 logs 为已退款
            $log->is_refund = 1;
            $log->save();
        }

        // 触发拼团失败行为
        $data = ['groupon' => $activityGroupon];
        \think\Hook::listen('activity_groupon_fail', $data);

        return true;
    }



    /**
     * 检查团状态
     */
    protected function checkGrouponStatus($activityGroupon) {
        if (!$activityGroupon) {
            return true;
        }

        // 重新获取团信息
        $activityGroupon = ActivityGroupon::where('id', $activityGroupon['id'])->find();
        if ($activityGroupon['current_num'] >= $activityGroupon['num'] && !in_array($activityGroupon['status'], ['finish', 'finish-fictitious'])) {
            // 查询是否有虚拟团成员
            $fictitiousCount = ActivityGrouponLog::where('groupon_id', $activityGroupon['id'])->where('is_fictitious', 1)->count();

            // 将团设置为已完成
            $activityGroupon->status = $fictitiousCount ? 'finish-fictitious' : 'finish';
            $activityGroupon->finishtime = time();
            $activityGroupon->save();

            // 触发成团行为
            $data = ['groupon' => $activityGroupon];
            \think\Hook::listen('activity_groupon_finish', $data);
        }

        return true;
    }
}
