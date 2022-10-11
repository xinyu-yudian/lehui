<?php

namespace addons\shopro\notifications\store;

use addons\shopro\notifications\Notification;
use think\queue\ShouldQueue;
use addons\shopro\model\Config;
use addons\shopro\model\DispatchAutosend;
use addons\shopro\model\Store;
use addons\shopro\model\User;
use addons\shopro\model\UserOauth;

/**
 * 门店审核结果通知
 */
class Apply extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: store_apply: 门店审核结果通知
    public $event = '';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'store_apply' => [
            'name' => '门店审核结果通知',
            'fields' => [
                ['name' => '申请用户', 'field' => 'nickname'],
                ['name' => '门店名称', 'field' => 'store_name'],
                ['name' => '联系人', 'field' => 'realname'],
                ['name' => '手机号', 'field' => 'phone'],
                ['name' => '所属区域', 'field' => 'region'],
                ['name' => '门店地址', 'field' => 'address'],
                ['name' => '审核状态', 'field' => 'status'],
                ['name' => '拒绝原因', 'field' => 'status_msg'],
                ['name' => '申请时间', 'field' => 'create_date'],
                ['name' => '处理时间', 'field' => 'update_date'],
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
        $apply = $data['apply'] ?? [];
        $store = $data['store'] ?? null;

        $params = [];
        $params['apply'] = $apply;
        $params['store'] = $store;

        // 获取消息data
        $this->paramsData($params, $notifiable);

        return $params;
    }


    public function toSms($notifiable) {
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


    public function toWxOfficeAccount($notifiable, $type = 'wxOfficialAccount') {
        $event = $this->event;
        $data = $this->data;
        $apply = $data['apply'] ?? [];
        $store = $data['store'] ?? null;

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxOfficialAccount')) {
            // 门店入口
            $path = "/pages/app/merchant/apply";        // 门店申请地址

            // 获取 h5 域名
            $url = $this->getH5DomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['url'] = $url;

            // 获取消息data
            $this->paramsData($params, $notifiable);
        }

        return $this->formatParams($params, $type);
    }


    public function toWxMiniProgram($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $apply = $data['apply'] ?? [];
        $store = $data['store'] ?? null;

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxMiniProgram')) {
            // 钱包记录
            $path = "/pages/app/merchant/apply";        // 门店申请地址

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
    private function paramsData(&$params, $notifiable) {
        $params['data'] = [];
        
        switch ($this->event) {
            case 'store_apply':        // 门店审核结果通知
                $params['data'] = array_merge($params['data'], $this->storeApplyData($notifiable));
                break;
            default:
                $params = [];
        }
    }


    /**
     * 申请提现消息参数
     */
    private function storeApplyData($notifiable) {
        $currentData = $this->data;
        $apply = $currentData['apply'] ?? [];
        $store = $currentData['store'] ?? null;

        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['nickname'] = $notifiable['nickname'];
        $data['store_name'] = $apply['name'];
        $data['realname'] = $apply['realname'];
        $data['phone'] = $apply['phone'];
        $data['region'] = $apply['province_name'] . '-' . $apply['city_name'] . '-' . $apply['area_name'];
        $data['address'] = $apply['address'];
        $data['status'] = $apply['status_text'];
        $data['status_msg'] = $apply['status_msg'] ? : '审核已通过';
        $data['create_date'] = date('Y-m-d H:i:s', $apply['createtime']);
        $data['update_date'] = date('Y-m-d H:i:s', $apply['updatetime']);

        return $data;
    }
}