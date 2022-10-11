<?php

namespace addons\shopro\notifications;

use think\queue\ShouldQueue;
use addons\shopro\model\Config;
use addons\shopro\model\DispatchAutosend;
use addons\shopro\model\Store;
use addons\shopro\model\User;
use addons\shopro\model\UserOauth;

/**
 * 钱包通知
 */
class Wallet extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: wallet_apply: 提现结果通知 wallet_change: 钱包变动通知 score_change: 积分变动通知
    public $event = '';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'wallet_apply' => [
            'name' => '提现结果通知',
            'fields' => [
                ['name' => '提现用户', 'field' => 'nickname'],
                ['name' => '提现方式', 'field' => 'apply_type'],
                ['name' => '提现金额', 'field' => 'money'],
                ['name' => '手续费', 'field' => 'charge_money'],
                ['name' => '提现信息', 'field' => 'apply_info'],
                ['name' => '提现状态', 'field' => 'status'],
                ['name' => '提现时间', 'field' => 'create_date'],
                ['name' => '处理时间', 'field' => 'update_date'],
            ]
        ],
        'wallet_change' => [
            'name' => '余额变动通知',
            'fields' => [
                ['name' => '变动用户', 'field' => 'nickname'],
                ['name' => '变动金额', 'field' => 'wallet'],
                ['name' => '变动原因', 'field' => 'type_name'],
                ['name' => '资金类型', 'field' => 'wallet_type'],
                ['name' => '当前余额', 'field' => 'surplus'],
                ['name' => '变动时间', 'field' => 'create_date'],
            ]
        ],
        'score_change' => [
            'name' => '积分变动通知',
            'fields' => [
                ['name' => '变动用户', 'field' => 'nickname'],
                ['name' => '变动数量', 'field' => 'wallet'],
                ['name' => '变动原因', 'field' => 'type_name'],
                ['name' => '资金类型', 'field' => 'wallet_type'],
                ['name' => '当前余额', 'field' => 'surplus'],
                ['name' => '变动时间', 'field' => 'create_date'],
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
        $walletLog = $data['walletLog'] ?? [];

        $params = [];
        $params['apply'] = $apply;
        $params['walletLog'] = $walletLog;

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

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxOfficialAccount')) {
            if ($event == "score_change") {
                // 积分记录
                $path = "/pages/user/wallet/score-balance";
            } else if ($event == 'wallet_apply') {
                // 提现记录
                $path = '/pages/user/wallet/withdraw-log';
            } else {
                // 钱包记录
                $path = "/pages/user/wallet/index";
            }

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

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxMiniProgram')) {
            if ($event == "score_change") {
                // 积分记录
                $path = "/pages/user/wallet/score-balance";
            } else if ($event == 'wallet_apply') {
                // 提现记录
                $path = '/pages/user/wallet/withdraw-log';
            } else {
                // 钱包记录
                $path = "/pages/user/wallet/index";
            }

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
            case 'wallet_apply':        // 钱包提现结果
                $params['data'] = array_merge($params['data'], $this->walletApplyData($notifiable));
                break;
            case 'wallet_change':        // 钱包记录变化
                $params['data'] = array_merge($params['data'], $this->walletScoreChangeData($notifiable));
                break;
            case 'score_change':        // 钱包提现结果
                $params['data'] = array_merge($params['data'], $this->walletScoreChangeData($notifiable));
                break;
            default:
                $params = [];
        }
    }


    /**
     * 申请提现消息参数
     */
    private function walletApplyData($notifiable) {
        $currentData = $this->data;
        $apply = $currentData['apply'] ?? [];

        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['nickname'] = $notifiable['nickname'];
        $data['apply_type'] = $apply['apply_type_text'];
        $data['money'] = $apply['money'];
        $data['charge_money'] = $apply['charge_money'];
        $applyInfo = array_values($apply['apply_info']);
        $data['apply_info'] = implode(',', $applyInfo);
        $data['status'] = $apply['status_text'];
        $data['create_date'] = date('Y-m-d H:i:s', $apply['createtime']);
        $data['update_date'] = date('Y-m-d H:i:s', $apply['updatetime']);
        return $data;
    }


    /**
     * 余额/积分 变动消息参数
     */
    private function walletScoreChangeData($notifiable) {
        $currentData = $this->data;
        $walletLog = $currentData['walletLog'] ?? [];
        $notifiable = User::where('id', $notifiable['id'])->find();
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['nickname'] = $notifiable['nickname'];
        $data['wallet'] = $walletLog['wallet'];
        $data['type_name'] = $walletLog['type_name'];
        $data['wallet_type'] = $walletLog['wallet_type_name'];
        $data['surplus'] = $notifiable[$walletLog['wallet_type']] ?? 0;
        $data['create_date'] = date('Y-m-d H:i:s', $walletLog['createtime']);

        return $data;
    }

}
