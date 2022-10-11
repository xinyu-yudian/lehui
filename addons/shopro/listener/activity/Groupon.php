<?php

namespace addons\shopro\listener\activity;

use addons\shopro\exception\Exception;
use addons\shopro\model\User;
use addons\shopro\model\Goods;
use addons\shopro\library\traits\ActivityCache;
use addons\shopro\model\Activity;
use addons\shopro\model\ActivityGrouponLog;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use addons\shopro\model\OrderAction;

/**
* 成团事件
*/
class Groupon
{

    // 拼团成功
    public function activityGrouponFinish(&$params) {
        $groupon = $params['groupon'];
        $goods = Goods::where('id', $groupon['goods_id'])->find();

        // 检测该团是否还有其他未支付的订单， 如果有，将订单改为交易关闭（后台虚拟成团情况，有人下单但是未支付）
        $this->invalidOrder($groupon, 'groupon_success');

        // 查询所有参与该团的真实用户 users & grouponLogs & grouponLeader
        extract($this->getActivityGrouponUsers($groupon));

        // 获取所有订单，判断是否需要自动发货，并且发货
        // 拿到所有订单 id 
        $orderIds = array_column($grouponLogs, 'order_id');
        // 获取该团的所有订单
        $orders = Order::where('id', 'in', $orderIds)->select();
        foreach ($orders as $order) {
            // 检测是否有需要自动发货的商品，并且发货
            $order->grouponCheckAndSend($order);
        }

        if ($users) {
            \addons\shopro\library\notify\Notify::send(
                $users, 
                new \addons\shopro\notifications\Groupon([
                    'groupon' => $groupon, 
                    'grouponLogs' => $grouponLogs, 
                    'grouponLeader' => $grouponLeader, 
                    'goods' => $goods, 
                    'event' => 'groupon_success'
                ])
            );
        }
    }

    
    // 拼团失败
    public function activityGrouponFail(&$params) {
        $groupon = $params['groupon'];
        $goods = Goods::where('id', $groupon['goods_id'])->find();

        // 检测该团是否还有其他未支付的订单， 如果有，将订单改为交易关闭（拼团到期自动解散，或者后台手动解散 之前，有人下单但是未支付）
        $this->invalidOrder($groupon, 'groupon_fail');

        // 查询所有参与该团的真实用户 users & grouponLogs & grouponLeader
        extract($this->getActivityGrouponUsers($groupon));

        if ($users) {
            \addons\shopro\library\notify\Notify::send(
                $users,
                new \addons\shopro\notifications\Groupon([
                    'groupon' => $groupon,
                    'grouponLogs' => $grouponLogs, 
                    'grouponLeader' => $grouponLeader, 
                    'goods' => $goods, 
                    'event' => 'groupon_fail'
                ])
            );
        }
    }


    // 查询所有参与该团的真实用户
    private function getActivityGrouponUsers($groupon) {
        $grouponLogs = ActivityGrouponLog::where('groupon_id', $groupon['id'])->where('is_fictitious', 0)->select();
        $user_ids = array_column($grouponLogs, 'user_id');

        // 所有用户
        $users = User::where('id', 'in', $user_ids)->select();

        // 团长
        $grouponLeader = null;
        foreach ($users as $key => $user) {
            if ($user['id'] == $groupon['user_id']) {
                $grouponLeader = $user;
                break;
            }
        }

        return compact("users", "grouponLogs", "grouponLeader");
    }


    /**
     * 将该团的所有未支付订单关闭
     *
     * @return void
     */
    private function invalidOrder($groupon, $type) {
        // 获取订单
        $orders = Order::where("find_in_set('groupon',activity_type)")->where('status', 0)->where(function($query) use ($groupon) {
            $query->whereExists(function ($query) use ($groupon) {
                $order_table_name = (new Order())->getQuery()->getTable();
                $table_name = (new OrderItem())->getQuery()->getTable();
                $query->table($table_name)->where('order_id=' . $order_table_name . '.id')->where('goods_id', $groupon['goods_id'])->where('activity_id', $groupon['activity_id']);
            });
        })->select();
        
        foreach ($orders as $key => $order) {
            if ($order['ext_arr']['buy_type'] == 'groupon' && $order['ext_arr']['groupon_id'] == $groupon['id']) {
                // 拼团，并且是当前团
                \think\Db::transaction(function () use ($order, $type) {
                    $data = ['order' => $order];
                    // 订单关闭前
                    \think\Hook::listen('order_close_before', $data);
                    // 执行关闭
                    $order->status = Order::STATUS_INVALID;
                    $order->ext = json_encode($order->setExt($order, ['invalid_time' => time()]));      // 取消时间
                    $order->save();
    
                    OrderAction::operAdd($order, null, null, 'system', $type == 'groupon_success' ? '已成团，未支付订单系统自动失效' : '团已解散，未支付订单系统自动失效');
    
                    // 订单自动关闭之后 行为 返还用户优惠券，积分
                    $data = ['order' => $order];
                    \think\Hook::listen('order_close_after', $data);
                });
            }
        }
    }
    
}
