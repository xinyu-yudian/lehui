<?php

namespace addons\shopro\notifications;

use think\queue\ShouldQueue;
use addons\shopro\model\Config;
use addons\shopro\model\DispatchAutosend;
use addons\shopro\model\Store;
use addons\shopro\model\UserOauth;

/**
 * 订单通知
 */
class Order extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: order_sended: 订单发货成功
    public $event = 'order_sended';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'order_sended' => [
            'name' => '订单发货通知',
            'fields' => [
                ['name' => '订单号', 'field' => 'order_sn'],
                ['name' => '订单金额', 'field' => 'order_amount'],
                ['name' => '发货时间', 'field' => 'dispatch_time'],
                ['name' => '商品名称', 'field' => 'goods_title'],
                ['name' => '商品规格', 'field' => 'goods_sku_text'],
                ['name' => '商品价格', 'field' => 'goods_price'],
                ['name' => '购买数量', 'field' => 'goods_num'],
                ['name' => '快递公司', 'field' => 'express_name'],
                ['name' => '快递单号', 'field' => 'express_no'],
                ['name' => '收件信息', 'field' => 'consignee'],
                ['name' => '门店名称', 'field' => 'store_name'],
                ['name' => '门店电话', 'field' => 'store_phone'],
                ['name' => '预约时间', 'field' => 'dispatch_date'],
                ['name' => '预留电话', 'field' => 'dispatch_phone'],
                ['name' => '发货内容', 'field' => 'autosend_content'],
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
        $item = $data['item'] ?? [];

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
            case 'order_sended':
                $params['data'] = array_merge($params['data'], $this->orderSendedData($notifiable, $order, $item));
                break;
            default:
                $params = [];
        }
    }


    private function orderSendedData($notifiable, $order, $item) {
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['order_sn'] = $order['order_sn'];
        $data['order_amount'] = '￥' . $order['total_amount'];
        $data['dispatch_time'] = ($order['ext_arr'] && isset($order['ext_arr']['send_time'])) ?
                                    date('Y-m-d H:i:s', $order['ext_arr']['send_time']) : date('Y-m-d H:i:s');
        $data['goods_title'] = $item['goods_title'];
        $data['goods_sku_text'] = $item['goods_sku_text'];
        $data['goods_price'] = '￥' . $item['goods_price'];
        $data['goods_num'] = $item['goods_num'];
        $data['express_name'] = $item['express_name'];
        $data['express_no'] = $item['express_no'];
        $data['consignee'] = $order['consignee'] ? ($order['consignee'] . '-' . $order['phone']) : '';

        // 多发货方式字段
        $data['store_name'] = '';
        $data['store_phone'] = '';
        if (in_array($item['dispatch_type'], ['store', 'selfetch']) && $item['store_id']) {
            // 查询门店
            $store = Store::where('id', $item['store_id'])->find();
            if ($store) {
                $data['store_name'] = $store['name'];
                $data['store_phone'] = $store['phone'];
            }
        }

        // 初始化发货内容
        $data['dispatch_date'] = '';
        $data['dispatch_phone'] = '';
        $data['autosend_content'] = '';
        if ($item['ext_arr']) {
            $ext = $item['ext_arr'];
            $data['dispatch_date'] = $ext['dispatch_date'] ?? '';
            $data['dispatch_phone'] = $ext['dispatch_phone'] ?? '';

            if (isset($ext['autosend_type'])) {
                // 默认 text
                $data['autosend_content'] = $ext['autosend_content'] ?? '';
                if ($ext['autosend_type'] == 'params') {
                    // 自定义内容，将参数拼接成字符串
                    $data['autosend_content'] = $autosend_content = DispatchAutosend::getParamsContentText($ext['autosend_content']);
                } else if ($ext['autosend_type'] == 'card') {
                    // 电子卡密，待补充

                }
            }
        }
        

        return $data;
    }
}
