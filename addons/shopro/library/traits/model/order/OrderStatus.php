<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\model\ActivityGroupon;
use addons\shopro\library\Wechat;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use think\Cache;

trait OrderStatus
{

    public function getStatusNameAttr($value, $data)
    {
        return $this->getStatus($data, 'status_name');
    }

    public function getStatusDescAttr($value, $data)
    {
        return $this->getStatus($data, 'status_desc');
    }


    public function getBtnsAttr($value, $data)
    {
        return $this->getStatus($data, 'btns');
    }


    // 获取订单状态
    public function getStatusCodeAttr($value, $data)
    {
        $status_code = '';

        switch ($data['status']) {
            case Order::STATUS_CANCEL:
                $status_code = 'cancel';        // 订单已取消
                break;
            case Order::STATUS_INVALID:
                $status_code = 'invalid';        // 订单交易关闭
                break;
            case Order::STATUS_NOPAY:
                $status_code = 'nopay';        // 订单等待支付
                break;
            case Order::STATUS_PAYED:
                // 根据 item 获取支付后的状态信息
                $status_code = $this->getPayedStatusCode($data);
                break;
            case Order::STATUS_FINISH:
                $status_code = 'finish';
                break;
        }

        return $status_code;
    }


    /**
     * 获取支付成功之后的子状态
     */
    public function getPayedStatusCode($data)
    {
        $status_code = '';

        // 循环判断 item 状态
        $itemStatusCode = [];
        foreach ($this->item as $key => $item) {
            // 获取 item status
            $itemStatusCode[] = (new OrderItem)->getBaseStatusCode($item->toArray(), 'order');
        }

        // 取出不重复不为空的 status_code
        $statusCodes = array_unique(array_filter($itemStatusCode));

        if (in_array('commented', $statusCodes)) {
            // 存在已评价，就是已评价
            $status_code = 'commented';        // 走这里，说明只评价了一部分
        } else if (in_array('nocomment', $statusCodes)) {
            // 存在待评价，就是待评价
            $status_code = 'nocomment';
        } else if (in_array('noget', $statusCodes) || in_array('nosend', $statusCodes)) {
            if (get_class($this) == 'addons\shopro\model\Order') {
                // 前台接口，优先 待收货
                if (in_array('noget', $statusCodes)) {
                    // 存在待收货，就是待收货
                    $status_code = 'noget';
                } else {
                    $status_code = 'nosend';
                }
            } else {
                // 后台，优先待发货
                if (in_array('nosend', $statusCodes)) {
                    // 存在待发货就是待发货
                    $status_code = 'nosend';
                } else {
                    $status_code = 'noget';
                }
            }
        } else if (in_array('refund_finish', $statusCodes)) {
            // 都在退款完成
            $status_code = 'refund_finish';
        } else if (in_array('refund_ing', $statusCodes)) {
            // 都在退款中
            $status_code = 'refund_ing';
        } // 售后都不在总状态显示


        $ext_arr = json_decode($data['ext'], true);
        // 是拼团订单
        if (
            strpos($data['activity_type'], 'groupon') !== false &&
            isset($ext_arr['groupon_id']) && $ext_arr['groupon_id']
        ) {
            $groupon = ActivityGroupon::where('id', $ext_arr['groupon_id'])->find();
            if ($groupon) {
                if ($groupon['status'] == 'ing') {
                    // 尚未成团
                    $status_code = in_array('refund_finish', $statusCodes) ? 'refund_finish' : 'groupon_ing';       // 如果订单已退款，则是退款完成，不显示拼团中
                } else if ($groupon['status'] == 'invalid') {
                    $status_code = 'groupon_invalid';
                }
            }
        }

        return $status_code;
    }


    /**
     * 处理未支付 item status_code
     * 查询列表 item status_code 不关联订单表,使用这个方法进行处理
     */
    public static function setOrderItemStatusByOrder($order)
    {
        $order = is_array($order) ? $order : $order->toArray();

        foreach ($order['item'] as $key => &$item) {
            if (!in_array($order['status'], [Order::STATUS_PAYED, Order::STATUS_FINISH])) {
                // 未支付，status_code = 订单的 status_code
                $item['status_code'] = $order['status_code'];
                $item['status_name'] = '';
                $item['status_text'] = '';
                $item['status_desc'] = '';
                $item['btns'] = [];
            }
        }

        return $order;
    }
}
