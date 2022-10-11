<?php

namespace addons\shopro\listener\order;

use addons\shopro\exception\Exception;
use addons\shopro\library\traits\ActivityCache;
use addons\shopro\library\traits\Groupon;
use addons\shopro\model\ActivityGoodsSkuPrice;
use addons\shopro\model\Goods;
use addons\shopro\model\GoodsSkuPrice;
use addons\shopro\model\User;
use addons\shopro\model\UserCoupons;
use addons\shopro\library\traits\StockSale;

/**
 * 订单失效
 */
class Invalid
{
    use StockSale, ActivityCache, Groupon;

    // 订单取消后行为
    public function orderCancelAfter(&$params)
    {
        $order = $params['order'];

        $this->invalid($order, 'cancel');

        return $order;
    }


    // 订单关闭后行为
    public function orderCloseAfter(&$params)
    {
        $order = $params['order'];

        $this->invalid($order, 'close');
    }



    // 订单取消或关闭返还
    private function invalid($order, $type) {
        // 如果有优惠券， 返还优惠券
        if ($order->coupons_id) {
            $coupon = UserCoupons::where('id', $order->coupons_id)->find();

            if ($coupon) {
                $coupon->usetime = null;
                $coupon->save();
            }
        }

        // 退回积分
        if ($order->score_fee > 0) {
            // 退回积分
            User::score($order->score_fee, $order->user_id, 'score_back_order', $order->id, '', [
                'order_id' => $order->id,
                'order_sn' => $order->order_sn,
            ]);
        }

        
        // 预销量预库存只有 商城订单有
        if ($order['type'] == 'goods') {
            // 扣除预拼团人数
            $this->grouponCacheBackNum($order);
            
            // 判断扣除预销量
            $this->cacheBackSale($order);
        }

        return $order;

    }
}
