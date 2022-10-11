<?php

namespace addons\shopro\notifications;

use think\queue\ShouldQueue;
use addons\shopro\model\Config;
use addons\shopro\model\UserOauth;

/**
 * 退款通知
 */
class Refund extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: refund_agree: 退款同意, refund_refuse: 退款拒绝
    public $event = '';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'refund_agree' => [
            'name' => '退款成功通知',
            'fields' => [
                ['name' => '订单号', 'field' => 'order_sn'],
                ['name' => '订单金额', 'field' => 'order_amount'],
                ['name' => '用户昵称', 'field' => 'nickname'],
                ['name' => '商品名称', 'field' => 'goods_title'],
                ['name' => '商品规格', 'field' => 'goods_sku_text'],
                ['name' => '商品价格', 'field' => 'goods_price'],
                ['name' => '购买数量', 'field' => 'goods_num'],
                ['name' => '退款金额', 'field' => 'refund_money'],
                ['name' => '退款时间', 'field' => 'refund_time']
            ]
        ],
        // 'refund_refuse' => [     // 2021-01-08 退款拒绝已废弃
        //     'name' => '退款拒绝通知',
        //     'fields' => [
        //         ['name' => '订单号', 'field' => 'order_sn'],
        //         ['name' => '订单金额', 'field' => 'order_amount'],
        //         ['name' => '用户昵称', 'field' => 'nickname'],
        //         ['name' => '商品名称', 'field' => 'goods_title'],
        //         ['name' => '商品规格', 'field' => 'goods_sku_text'],
        //         ['name' => '商品价格', 'field' => 'goods_price'],
        //         ['name' => '购买数量', 'field' => 'goods_num'],
        //         ['name' => '拒绝原因', 'field' => 'refund_msg'],
        //     ]
        // ]
    ];

    public function __construct($data = [])
    {
        $this->data = $data;
        $this->event = $data['event'] ?? '';

        $this->initConfig();
    }


    public function toDatabase($notifiable) {
        $data = $this->data;
        $order = $data['order'];
        $item = $data['item'] ?? [];

        $params = [];
        $params['order'] = $order;
        $params['item'] = $item;

        // 获取消息data
        $this->paramsData($params, $notifiable, $order, $item);

        return $params;
    }


    public function toSms($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $order = $data['order'];
        $item = $data['item'] ?? [];

        $phone = $notifiable['mobile'] ? $notifiable['mobile'] : '';
        $params = [];
        $params['phone'] = $phone;

        // 获取消息data
        $this->paramsData($params, $notifiable, $order, $item);

        return $this->formatParams($params, 'sms');
    }


    public function toEmail($notifiable)
    {
        $event = $this->event;
        $data = $this->data;
        $order = $data['order'];
        $item = $data['item'] ?? [];

        $params = [];

        // 获取消息data
        $this->paramsData($params, $notifiable, $order, $item);

        return $this->formatParams($params, 'email');
    }


    public function toWxOfficeAccount($notifiable, $type = 'wxOfficialAccount') {
        $event = $this->event;
        $data = $this->data;
        $order = $data['order'];
        $item = $data['item'] ?? [];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxOfficialAccount')) {
            // 订单详情
            $path = "/pages/order/detail?id=" . $order['id'];

            // 获取 h5 域名
            $url = $this->getH5DomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['url'] = $url;

            // 获取消息data
            $this->paramsData($params, $notifiable, $order, $item);
        }

        return $this->formatParams($params, $type);
    }


    public function toWxMiniProgram($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $order = $data['order'];
        $item = $data['item'];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxMiniProgram')) {
            // 订单详情
            $path = "/pages/order/detail?id=" . $order['id'];

            // 获取小程序完整路径
            $path = $this->getMiniDomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['page'] = $path;

            // 获取消息data
            $this->paramsData($params, $notifiable, $order, $item);
        }

        return $this->formatParams($params, 'wxMiniProgram');
    }




    private function paramsData(&$params, $notifiable, $order, $item) {
        $params['data'] = [];
        switch ($this->event) {
            case 'refund_agree':
                $params['data'] = array_merge($params['data'], $this->refundAgreeData($notifiable, $order, $item));
                break;
            case 'refund_refuse':
                $params['data'] = array_merge($params['data'], $this->refundRefuseData($notifiable, $order, $item));
                break;
            default:
                $params = [];
        }
    }


    private function refundAgreeData($notifiable, $order, $item) {
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['order_sn'] = $order['order_sn'];
        $data['order_amount'] = '￥' . $order['total_amount'];
        $data['nickname'] = $notifiable['nickname'];
        $data['goods_title'] = $item['goods_title'];
        $data['goods_sku_text'] = $item['goods_sku_text'];
        $data['goods_price'] = '￥' . $item['goods_price'];
        $data['goods_num'] = $item['goods_num'];
        $data['refund_money'] = '￥' . $item['refund_fee'];
        $data['refund_time'] = date('Y-m-d H:i:s', $item['updatetime']);
        return $data;
    }


    private function refundRefuseData($notifiable, $order, $item) {
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['order_sn'] = $order['order_sn'];
        $data['order_amount'] = '￥' . $order['total_amount'];
        $data['nickname'] = $notifiable['nickname'];
        $data['goods_title'] = $item['goods_title'];
        $data['goods_sku_text'] = $item['goods_sku_text'];
        $data['goods_price'] = '￥' . $item['goods_price'];
        $data['goods_num'] = $item['goods_num'];
        $data['refund_msg'] = $item['refund_msg'];

        return $data;
    }
}
