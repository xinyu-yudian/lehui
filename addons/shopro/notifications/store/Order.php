<?php

namespace addons\shopro\notifications\store;

use addons\shopro\notifications\Notification;
use think\queue\ShouldQueue;
use addons\shopro\model\User;

/**
 * 门店订单通知
 */
class Order extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: store_order_new: 门店新订单通知
    public $event = 'store_order_new';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'store_order_new' => [
            'name' => '门店新订单通知(仅门店)',
            'fields' => [
                ['name' => '下单用户', 'field' => 'nickname'],
                ['name' => '门店名称', 'field' => 'store_name'],
                ['name' => '订单号', 'field' => 'order_sn'],
                ['name' => '订单金额', 'field' => 'total_amount'],
                ['name' => '支付金额', 'field' => 'pay_fee'],
                ['name' => '下单时间', 'field' => 'create_date'],
                ['name' => '支付时间', 'field' => 'pay_date']
            ]
        ]
    ];


    public function __construct($data = [])
    {
        $this->data = $data;
        $this->event = $data['event'] ?? '';

        $this->initConfig();
    }


    public function toDatabase($notifiable)
    {
        $data = $this->data;
        $store = $data['store'] ?? [];
        $order = $data['order'] ?? [];

        $params = [];
        $params['store'] = $store;
        $params['order'] = $order;

        // 获取消息data
        $this->paramsData($params, $notifiable);

        return $params;
    }


    public function toSms($notifiable)
    {
        $event = $this->event;
        $data = $this->data;

        $phone = $notifiable['mobile'] ? $notifiable['mobile'] : '';
        $params = [];
        $params['phone'] = $phone;

        // 获取消息data
        $this->paramsData($params, $notifiable);

        return $this->formatParams($params, 'sms');
    }


    public function toEmail($notifiable)
    {
        $event = $this->event;
        $data = $this->data;

        $params = [];

        // 获取消息data
        $this->paramsData($params, $notifiable);

        return $this->formatParams($params, 'email');
    }



    public function toWxOfficeAccount($notifiable, $type = 'wxOfficialAccount')
    {
        $event = $this->event;
        $data = $this->data;
        $store = $data['store'] ?? [];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxOfficialAccount')) {
            // 门店首页
            $path = "/pages/app/merchant/index?storeId=" . $store['id'];

            // 获取 h5 域名
            $url = $this->getH5DomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['url'] = $url;

            // 获取消息data
            $this->paramsData($params, $notifiable);
        }

        return $this->formatParams($params, $type);
    }


    public function toWxMiniProgram($notifiable)
    {
        $event = $this->event;
        $data = $this->data;
        $store = $data['store'] ?? [];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxMiniProgram')) {
            // 门店首页
            $path = "/pages/app/merchant/index?storeId=" . $store['id'];

            // 获取小程序完整路径
            $path = $this->getMiniDomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['page'] = $path;

            // 获取消息data
            $this->paramsData($params, $notifiable);
        }

        return $this->formatParams($params, 'wxMiniProgram');
    }



    // 组合消息参数
    private function paramsData(&$params, $notifiable)
    {
        $params['data'] = [];

        switch ($this->event) {
            case 'store_order_new':        // 钱包提现结果
                $params['data'] = array_merge($params['data'], $this->storeOrderNewData($notifiable));
                break;
            default:
                $params = [];
        }
    }


    /**
     * 申请提现消息参数
     */
    private function storeOrderNewData($notifiable)
    {
        $currentData = $this->data;
        $store = $currentData['store'] ?? [];
        $order = $currentData['order'] ?? [];

        // 下单用户
        $user = User::where('id', $order['user_id'])->find();

        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['nickname'] = $user ? $user['nickname'] : '';
        $data['store_name'] = $store ? $store['name'] : '';
        $data['order_sn'] = $order['order_sn'];
        $data['total_amount'] = $order['total_amount'];
        $data['pay_fee'] = $order['pay_fee'];
        $data['create_date'] = date('Y-m-d H:i:s', $order['createtime']);
        $data['pay_date'] = date('Y-m-d H:i:s', $order['paytime']);

        return $data;
    }
}
