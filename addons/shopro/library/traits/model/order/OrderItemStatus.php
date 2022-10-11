<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\model\ActivityGroupon;
use addons\shopro\library\Wechat;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use think\Cache;

trait OrderItemStatus
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


    // 获取订单 item status_code 状态，不进行订单是否支付判断，在这里查询数据库特别慢，
    // 需要处理情况，订单列表：要正确显示item 状态，直接获取 item 的状态
    public function getStatusCodeAttr($value, $data)
    {
        $status_code = 'null';

        // $order = Order::withTrashed()->where('id', $data['order_id'])->find();
        // if (!$order) {
        //     return $status_code;
        // }

        // // 判断是否支付
        // if (!in_array($order->status, [Order::STATUS_PAYED, Order::STATUS_FINISH])) {
        //     return $order->status_code;
        // }

        // 获取 item status_code
        return $this->getBaseStatusCode($data);
    }


    /**
     * $data 当前 item 数据
     * $from 当前 model 调用，还是 order 调用
     */
    public function getBaseStatusCode($data, $from = 'item')
    {
        $status_code = 'null';

        if (in_array($data['refund_status'], [OrderItem::REFUND_STATUS_OK, OrderItem::REFUND_STATUS_FINISH])) {
            // 退款完成
            return 'refund_finish';
        }

        if ($data['aftersale_status'] || $data['refund_status']) {
            // 申请了售后或者退款
            if ($data['refund_status'] != OrderItem::REFUND_STATUS_NOREFUND) {
                // 退款驳回或者退款中

                // status_code
                $status_code = $this->getNormalStatusCode($data);

                switch ($data['refund_status']) {
                    case OrderItem::REFUND_STATUS_ING:
                        $status_code = 'refund_ing';
                        break;
                    case OrderItem::REFUND_STATUS_REFUSE:
                        $status_code = 'refund_refuse';
                        break;
                }
            } else {
                // 只申请了售后，没有退款

                // status_code
                $status_code = $this->getNormalStatusCode($data);

                // item 要原始状态，总订单还要原来的未退款状态
                if ($from == 'item') {
                    switch ($data['aftersale_status']) {
                        case OrderItem::AFTERSALE_STATUS_REFUSE:
                            $status_code = 'after_refuse' . '|' . $status_code;
                            break;
                        case OrderItem::AFTERSALE_STATUS_AFTERING:
                            $status_code = 'after_ing' . '|' . $status_code;
                            break;
                        case OrderItem::AFTERSALE_STATUS_OK:
                            $status_code = 'after_finish' . '|' . $status_code;
                            break;
                    }
                }
            }
        } else {
            // 未申请售后&退款

            // status_code
            $status_code = $this->getNormalStatusCode($data);
        }

        return $status_code;
    }



    public function getNormalStatusCode($data)
    {
        // 获取未申请售后和退款时候的 status_code
        $status_code = 'null';

        switch ($data['dispatch_status']) {
            case OrderItem::DISPATCH_STATUS_NOSEND:
                $status_code = 'nosend';
                break;
            case OrderItem::DISPATCH_STATUS_SENDED:
                $status_code = 'noget';
                break;
            case OrderItem::DISPATCH_STATUS_GETED:
                if ($data['comment_status'] == OrderItem::COMMENT_STATUS_NO) {
                    $status_code = 'nocomment';
                } else {
                    $status_code = 'commented';
                }
                break;
        }

        return $status_code;        // status_code
    }


    public function setExt($item, $field, $origin = [])
    {
        $newExt = array_merge($origin, $field);

        $itemExt = $item['ext_arr'];

        return array_merge($itemExt, $newExt);
    }
}
