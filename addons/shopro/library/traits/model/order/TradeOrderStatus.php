<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\model\ActivityGroupon;
use addons\shopro\library\Wechat;
use addons\shopro\model\Order;
use addons\shopro\model\OrderItem;
use think\Cache;

trait TradeOrderStatus
{

    public function getPayTypeList()
    {
        return ['wechat' => '微信', 'alipay' => "支付宝", 'wallet' => "钱包", 'score' => "积分"];
    }

    public function getPlatformList()
    {
        return ['H5' => "H5", 'wxOfficialAccount' => "公众号", 'wxMiniProgram' => "小程序", 'App' => "App"];
    }

    /* -------------------------- 访问器 ------------------------ */

    public function getPayTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_type']) ? $data['pay_type'] : '');
        $list = $this->getPayTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getPlatformTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['platform']) ? $data['platform'] : '');
        $list = $this->getPlatformList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getExtArrAttr($value, $data)
    {
        $ext = (isset($data['ext']) && $data['ext']) ? json_decode($data['ext'], true) : [];

        return $ext;
    }



    public function getStatusNameAttr($value, $data)
    {
        return $this->getStatus($data, 'status_name');
    }

    public function getStatusDescAttr($value, $data)
    {
        return $this->getStatus($data, 'status_desc');
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
                $status_code = 'payed';
                break;
            case Order::STATUS_FINISH:
                // 根据 item 获取支付后的状态信息
                $status_code = 'finish';
                break;
        }

        return $status_code;
    }


    protected function getStatus($data, $type)
    {
        $btns = [];
        $status_name = '';
        $status_desc = '';

        switch ($this->status_code) {
            case 'cancel':
                $status_name = '已取消';
                $status_desc = '订单已取消';
                break;
            case 'invalid':
                $status_name = '交易关闭';
                $status_desc = '交易关闭';
                break;
            case 'nopay':
                $status_name = '待付款';
                $status_desc = '等待用户付款';
                break;
            case 'payed':
                $status_name = '已支付';
                $status_desc = '订单已支付';
                break;
            case 'finish':
                $status_name = '已完成';
                $status_desc = '订单已完成';
                break;
        }

        return $type == 'status_name' ? $status_name : $status_desc;
    }


    public function setExt($order, $field, $origin = [])
    {
        $newExt = array_merge($origin, $field);

        $orderExt = $order['ext_arr'];

        return array_merge($orderExt, $newExt);
    }


    // 已失效
    public function scopeInvalid($query)
    {
        return $query->where('status', Order::STATUS_INVALID);
    }

    // 已取消
    public function scopeCancel($query)
    {
        return $query->where('status', Order::STATUS_CANCEL);
    }

    // 未支付
    public function scopeNopay($query)
    {
        return $query->where('status', Order::STATUS_NOPAY);
    }

    // 已支付
    public function scopePayed($query)
    {
        return $query->where('status', Order::STATUS_PAYED);
    }

    // 已完成
    public function scopeFinish($query)
    {
        return $query->where('status', Order::STATUS_FINISH);
    }
}
