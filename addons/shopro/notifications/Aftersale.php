<?php

namespace addons\shopro\notifications;

use think\queue\ShouldQueue;
use addons\shopro\model\Config;
use addons\shopro\model\DispatchAutosend;
use addons\shopro\model\Store;
use addons\shopro\model\UserOauth;

/**
 * 售后通知
 */
class Aftersale extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: aftersale_change: 售后结果通知
    public $event = 'aftersale_change';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'aftersale_change' => [
            'name' => '售后结果通知',
            'fields' => [
                ['name' => '售后单号', 'field' => 'aftersale_sn'],
                ['name' => '申请时间', 'field' => 'apply_date'],
                ['name' => '订单号', 'field' => 'order_sn'],
                ['name' => '下单时间', 'field' => 'create_date'],
                ['name' => '支付金额', 'field' => 'pay_fee'],
                ['name' => '售后类型', 'field' => 'aftersale_type'],
                ['name' => '联系电话', 'field' => 'aftersale_phone'],
                ['name' => '商品名称', 'field' => 'goods_title'],
                ['name' => '商品规格', 'field' => 'goods_sku_text'],
                ['name' => '商品原价', 'field' => 'goods_original_price'],
                ['name' => '商品价格', 'field' => 'goods_price'],
                ['name' => '购买数量', 'field' => 'goods_num'],
                ['name' => '优惠金额', 'field' => 'discount_fee'],
                ['name' => '售后状态', 'field' => 'aftersale_status_text'],
                ['name' => '退款状态', 'field' => 'refund_status_text'],
                ['name' => '退款金额', 'field' => 'refund_fee'],
                ['name' => '退款原因', 'field' => 'reason'],
                ['name' => '退款描述', 'field' => 'content'],
                ['name' => '处理时间', 'field' => 'oper_date'],
            ]
        ]
    ];


    public function __construct($data = [])
    {
        $this->data = $data;
        $this->event = $data['event'] ?? '';
        
        $this->initConfig();
    }


    public function toDatabase($notifiable) {
        $data = $this->data;
        $aftersale = $data['aftersale'] ?? [];
        $order = $data['order'] ?? [];
        $aftersaleLog = $data['aftersaleLog'] ?? [];

        $params = [];
        $params['aftersale'] = $aftersale;
        $params['order'] = $order;
        $params['aftersaleLog'] = $aftersaleLog;

        // 获取消息data
        $this->paramsData($params, $notifiable, $aftersale, $order, $aftersaleLog);
        return $params;
    }


    public function toSms($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $aftersale = $data['aftersale'] ?? [];
        $order = $data['order'] ?? [];
        $aftersaleLog = $data['aftersaleLog'] ?? [];

        $phone = $notifiable['mobile'] ? $notifiable['mobile'] : '';
        $params = [];
        $params['phone'] = $phone;
      
        // 获取消息data
        $this->paramsData($params, $notifiable, $aftersale, $order, $aftersaleLog);

        return $this->formatParams($params, 'sms');
    }


    public function toEmail($notifiable)
    {
        $event = $this->event;
        $data = $this->data;
        $aftersale = $data['aftersale'] ?? [];
        $order = $data['order'] ?? [];
        $aftersaleLog = $data['aftersaleLog'] ?? [];

        $params = [];

        // 获取消息data
        $this->paramsData($params, $notifiable, $aftersale, $order, $aftersaleLog);

        return $this->formatParams($params, 'email');
    }


    public function toWxOfficeAccount($notifiable, $type = 'wxOfficialAccount') {
        $event = $this->event;
        $data = $this->data;
        $aftersale = $data['aftersale'] ?? [];
        $order = $data['order'] ?? [];
        $aftersaleLog = $data['aftersaleLog'] ?? [];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxOfficialAccount')) {
            // 售后详情
            $path = "/pages/order/after-sale/detail?aftersaleId=" . $aftersale['id'];

            // 获取 h5 域名
            $url = $this->getH5DomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['url'] = $url;

            // 获取消息data
            $this->paramsData($params, $notifiable, $aftersale, $order, $aftersaleLog);
        }

        return $this->formatParams($params, $type);
    }


    public function toWxMiniProgram($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $aftersale = $data['aftersale'] ?? [];
        $order = $data['order'] ?? [];
        $aftersaleLog = $data['aftersaleLog'] ?? [];

        $params = [];
        
        if ($oauth = $this->getWxOauth($notifiable, 'wxMiniProgram')) {
            // 售后详情
            $path = "/pages/order/after-sale/detail?aftersaleId=" . $aftersale['id'];

            // 获取小程序完整路径
            $path = $this->getMiniDomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['page'] = $path;

            // 获取消息data
            $this->paramsData($params, $notifiable, $aftersale, $order, $aftersaleLog);
        }

        return $this->formatParams($params, 'wxMiniProgram');
    }



    private function paramsData(&$params, $notifiable, $aftersale, $order, $aftersaleLog) {
        $params['data'] = [];
        
        switch ($this->event) {
            case 'aftersale_change':
                $params['data'] = array_merge($params['data'], $this->aftersaleChangeData($notifiable, $aftersale, $order, $aftersaleLog));
                break;
            default:
                $params = [];
        }
    }


    private function aftersaleChangeData($notifiable, $aftersale, $order, $aftersaleLog) {
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['aftersale_sn'] = $aftersale['aftersale_sn'];
        $data['apply_date'] = date('Y-m-d H:i:s', $aftersale['createtime']);
        $data['order_sn'] = $order['order_sn'] ?? '';
        $data['create_date'] = date('Y-m-d H:i:s', ($order['createtime'] ?? 0));
        $data['pay_fee'] = ($order && $order['pay_fee']) ? ('￥' . $order['pay_fee']) : '';
        $data['aftersale_type'] = $aftersale['type_text'];
        $data['aftersale_phone'] = $aftersale['phone'];
        $data['goods_title'] = $aftersale['goods_title'];
        $data['goods_sku_text'] = $aftersale['goods_sku_text'];
        $data['goods_original_price'] = '￥' . $aftersale['goods_original_price'];
        $data['goods_price'] = '￥' . $aftersale['goods_price'];
        $data['discount_fee'] = '￥' . $aftersale['discount_fee'];
        $data['goods_num'] = $aftersale['goods_num'];
        $data['aftersale_status_text'] = $aftersale['aftersale_status_text'];
        $data['refund_status_text'] = $aftersale['refund_status_text'];
        if ($aftersale['refund_fee']) {
            $data['refund_fee'] = '￥' . $aftersale['refund_fee'];
        } else {
            $data['refund_fee'] = '暂未退款';
        }
        $data['reason'] = $aftersaleLog['reason']  ? : '-';
        $data['content'] = $aftersaleLog['content'] ? strip_tags($aftersaleLog['content']) : '-';
        $data['oper_date'] = date('Y-m-d H:i:s', $aftersaleLog['createtime']);

        return $data;
    }
}
