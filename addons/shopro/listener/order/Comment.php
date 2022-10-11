<?php

namespace addons\shopro\listener\order;

use addons\shopro\exception\Exception;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use addons\shopro\model\OrderAction;
use addons\shopro\model\User;

/**
 * 评价事件
 */
class Comment
{

    // 评价之后
    public function orderCommentAfter(&$params) {
        $order = $params['order'];

        // 获取订单，判断订单是否全部评价
        $order = Order::with('item')->where('id', $order['id'])->find();
        $user = User::where('id', $order['user_id'])->find();

        // 更新评价时间
        $order->ext = json_encode($order->setExt($order, ['comment_time' => time()]));      // 收货时间
        $order->save();

        // 判断所有产品是否都已评价完成
        $is_finish = true;
        foreach ($order->item as $key => $orderItem) {
            if ($orderItem->comment_status != OrderItem::COMMENT_STATUS_OK) {
                // 存在未评价商品
                $is_finish = false;
            }
        }

        // 订单已完成
        if ($is_finish) {
            $order->status = Order::STATUS_FINISH;
            $order->ext = json_encode($order->setExt($order, ['finish_time' => time()]));
            $order->save();
            OrderAction::operAdd($order, null, $user, 'user', '交易完成');
            $params = [
                'order' => $order
            ];
            \think\Hook::listen('order_finish', $params);
        }

        return $order;
    }

}
