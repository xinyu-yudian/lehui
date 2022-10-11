<?php

namespace addons\shopro\listener\order;

use addons\shopro\exception\Exception;
use addons\shopro\model\Cart;
use addons\shopro\model\Config;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use addons\shopro\model\User;
use addons\shopro\library\traits\StockSale;
use addons\shopro\library\traits\ActivityCache;
use addons\shopro\library\traits\Groupon;
use addons\shopro\model\ActivityGroupon;

/**
 * 订单创建
 */
class Create
{
    use StockSale, ActivityCache, Groupon;


    // 订单创建前
    public function orderCreateBefore(&$params) {
        $user = $params['user'];
        $order_type = $params['order_type'];
        $groupon_id = $params['groupon_id'];
        $buy_type = $params['buy_type'];
        $goods_original_amount = $params['goods_original_amount'];
        $goods_amount = $params['goods_amount'];
        $dispatch_amount = $params['dispatch_amount'];
        $total_amount = $params['total_amount'];
        $score_amount = $params['score_amount'];
        $total_fee = $params['total_fee'];
        $discount_fee = $params['discount_fee'];
        $coupon_fee = $params['coupon_fee'];
        $new_goods_list = $params['new_goods_list'];
        $activity_type = $params['activity_type'];
        $user_coupons = $params['user_coupons'];
        $user_address = $params['user_address'];
        $from = $params['from'];

        // 重新获取 user
        $user = User::where('id', $user->id)->find();

        // 如果需要支付积分
        if ($score_amount) {
            // 判断个人积分是否充足
            if ($user->score < $score_amount) {
                // 积分不足
                new Exception('积分不足');
            }
        }

        // 限购
        $this->limitBuy($new_goods_list, $user);

        // 是拼团， 并且不是单独购买 ，单独购买直接正常走商品流程
        if (strpos($activity_type, 'groupon') !== false && $buy_type != 'alone') {
            // 参与现有团
            if ($groupon_id) {
                // 获取当前团，并判断当前团是否可参与
                $activityGroupon = $this->checkJoinGroupon($new_goods_list[0], $user, $groupon_id);
            } else{
                // 开新团,放到 支付成功之后
                // $activityGroupon = $this->newGroupon($new_goods_list[0], $user);
            }
        }

        // 判断 并 增加 redis 销量
        if ($order_type == 'goods') {
            $this->cacheForwardSale($new_goods_list);
        }
        
        // （开新团不判断）参与旧团 增加预拼团人数，上面加入团的时候已经判断过一次了，所以这里 99.99% 会加入成功的
        if (isset($activityGroupon) && $activityGroupon) {
            // 将团信息缓存，用在后续下单流程
            $key = 'grouponinfo-' . $user['id'];
            cache($key, json_encode($activityGroupon), 60);

            // 增加拼团预成员人数, 拼团只能单独购买
            $detail = $new_goods_list[0]['detail'];
            $this->grouponCacheForwardNum($activityGroupon, $detail['activity'], $user);
        }
        
    }


    // 订单创建后
    public function orderCreateAfter(&$params)
    {
        $user = $params['user'];
        $order = $params['order'];
        $from = $params['from'];
        $groupon = $params['groupon'];
        $buy_type = $params['buy_type'];
        $new_goods_list = $params['new_goods_list'];

        // 删除购物车
        if ($from == 'cart') {
            foreach ($new_goods_list as $delCart) {
                Cart::where([
                    'user_id' => $user->id,
                    'goods_id' => $delCart['goods_id'],
                    'sku_price_id' => $delCart['sku_price_id'],
                ])->delete();
            }
        }

        // 更新订单扩展字段
        $order_ext = $order['ext_arr'];
        $order_ext['buy_type'] = $buy_type;     // 购买方式，alone： 单独购买， groupon: 拼团
        $order_ext['groupon_id'] = $groupon['id'] ?? 0; // 如果是拼团，团 id

        // 判断需要支付的金额是否大于 0 
        if ($order['total_fee'] <= 0) {
            // 更新订单扩展字段
            $order->ext = json_encode($order_ext);
            $order->save();

            $order = (new Order)->paymentProcess($order, [
                'order_sn' => $order['order_sn'],
                'transaction_id' => '',
                'notify_time' => date('Y-m-d H:i:s'),
                'buyer_email' => $user->id,
                'payment_json' => json_encode([]),
                'pay_fee' => $order->total_fee,
                'pay_type' => $order['type'] == 'score' ? 'score' : 'wallet'             // 支付方式 积分完全支付,或者不需要支付，使用 wallet
            ]);
        } else {
            // 默认取第一个商品
            $goods_one = $new_goods_list[0]['detail'];
            $activity_one = $goods_one['activity'];

            // 获取第一个商品的活动规则，活动不存在，自动会使用全局自动关闭
            $rules = $activity_one['rules'] ?? [];
            // 优先使用活动的订单关闭时间
            if (isset($rules['order_auto_close']) && $rules['order_auto_close'] > 0) {
                $close_minue = $rules['order_auto_close'];
            } else {
                // 添加自动关闭队列
                $config = Config::where('name', 'order')->cache(300)->find();        // 读取配置自动缓存 5 分钟
                $config = isset($config) ? json_decode($config['value'], true) : [];
                
                $close_minue = (isset($config['order_auto_close']) && $config['order_auto_close'] > 0)
                                    ? $config['order_auto_close'] : 15; 
            }

            // 更新订单，将过期时间存入订单，前台展示支付倒计时
            $order_ext['expired_time'] = time() + ($close_minue * 60);
            
            \think\Queue::later(($close_minue * 60), '\addons\shopro\job\OrderAutoOper@autoClose', ['order' => $order], 'shopro');
            
            // 更新订单扩展字段
            $order->ext = json_encode($order_ext);
            $order->save();
        }

        return $order;
    }



    /**
     * 判断是否限制购买
     */
    private function limitBuy($new_goods_list, $user) {
        foreach ($new_goods_list as $key => $goods) {
            $detail = $goods['detail'];
            $activity = $detail['activity'];

            $rules = $activity['rules'] ?? [];
            // 不存在，或者 0 不限制
            if (!isset($rules['limit_buy']) || $rules['limit_buy'] <= 0) {
                continue;
            }

            // 查询用户老订单，判断本次下单数量，判断是否超过购买限制, 未支付的或者已完成的都算
            $buy_num = OrderItem::where('user_id', $user['id'])->where('goods_id', $detail['id'])->where('activity_id', $activity['id'])
                ->whereExists(function ($query) use ($detail, $activity) {
                    $order_table_name = (new Order())->getQuery()->getTable();
                    $table_name = (new OrderItem())->getQuery()->getTable();

                    $query->table($order_table_name)->where('order_id=' . $order_table_name . '.id')
                        ->where('status', '>=', Order::STATUS_NOPAY);       // 未支付，或者已支付的订单都算
                })->sum('goods_num');

            if (($buy_num + $goods['goods_num']) > $rules['limit_buy']) {
                $msg = '该商品限购 ' . $rules['limit_buy'] . ' 件';

                if ($buy_num < $rules['limit_buy']) {
                    $msg .= '，当前还可购买 ' . ($rules['limit_buy'] - $buy_num) . ' 件';
                }

                new Exception($msg);
            }
        }
    }
}
