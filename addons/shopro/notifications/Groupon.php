<?php

namespace addons\shopro\notifications;

use addons\shopro\model\ActivityGrouponLog;
use think\queue\ShouldQueue;
use addons\shopro\model\Config;
use addons\shopro\model\UserOauth;
use addons\shopro\model\Order as OrderModel;

/**
 * 拼团通知
 */
class Groupon extends Notification implements ShouldQueue
{
    // 队列延迟时间，必须继承 ShouldQueue 接口
    public $delay = 0;

    // 发送类型 复合型消息类动态传值 当前类支持的发送类型: groupon_success: 拼团成功, groupon_fail: 拼团失败
    public $event = '';

    // 额外数据
    public $data = [];

    // 返回的字段列表
    public static $returnField = [
        'groupon_success' => [
            'name' => '拼团成功通知',
            'fields' => [
                ['name' => '商品名称', 'field' => 'goods_title'],
                ['name' => '拼团用户', 'field' => 'groupon_user'],
                ['name' => '团长', 'field' => 'groupon_leader'],
                ['name' => '参团金额', 'field' => 'groupon_price'],
                ['name' => '开团时间', 'field' => 'groupon_start_time'],
                ['name' => '成团时间', 'field' => 'groupon_finish_time'],
                ['name' => '成团人数', 'field' => 'groupon_num'],
                ['name' => '订单号', 'field' => 'order_sn']
            ]
        ],
        'groupon_fail' => [
            'name' => '拼团失败通知',
            'fields' => [
                ['name' => '商品名称', 'field' => 'goods_title'],
                ['name' => '拼团用户', 'field' => 'groupon_user'],
                ['name' => '团长', 'field' => 'groupon_leader'],
                ['name' => '参团金额', 'field' => 'groupon_price'],
                ['name' => '开团时间', 'field' => 'groupon_start_time'],
                ['name' => '参团人数', 'field' => 'groupon_current_num'],
                ['name' => '成团人数', 'field' => 'groupon_num'],
                ['name' => '订单号', 'field' => 'order_sn'],
                ['name' => '退款金额', 'field' => 'refund_money']
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
        $groupon = $data['groupon'];
        $grouponLogs = $data['grouponLogs'];
        $grouponLeader = $data['grouponLeader'];
        $goods = $data['goods'];

        $params = [];
        $params['groupon'] = $groupon;

        // 获取消息data
        $this->paramsData($params, $notifiable, $groupon, $grouponLogs, $grouponLeader, $goods);

        return $params;
    }


    public function toSms($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $groupon = $data['groupon'];
        $grouponLogs = $data['grouponLogs'];
        $grouponLeader = $data['grouponLeader'];
        $goods = $data['goods'];
        
        $phone = $notifiable['mobile'] ? $notifiable['mobile'] : '';
        $params = [];
        $params['phone'] = $phone;

        // 获取消息data
        $this->paramsData($params, $notifiable, $groupon, $grouponLogs, $grouponLeader, $goods);

        return $this->formatParams($params, 'sms');
    }


    public function toEmail($notifiable)
    {
        $event = $this->event;
        $data = $this->data;
        $groupon = $data['groupon'];
        $grouponLogs = $data['grouponLogs'];
        $grouponLeader = $data['grouponLeader'];
        $goods = $data['goods'];

        $params = [];

        // 获取消息data
        $this->paramsData($params, $notifiable, $groupon, $grouponLogs, $grouponLeader, $goods);

        return $this->formatParams($params, 'email');
    }


    public function toWxOfficeAccount($notifiable, $type = 'wxOfficialAccount') {
        $event = $this->event;
        $data = $this->data;
        $groupon = $data['groupon'];
        $grouponLogs = $data['grouponLogs'];
        $grouponLeader = $data['grouponLeader'];
        $goods = $data['goods'];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxOfficialAccount')) {
            // 拼团详情
            $path = "/pages/activity/groupon/detail?id=" . $groupon['id'];

            // 获取 h5 域名
            $url = $this->getH5DomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['url'] = $url;

            // 获取消息data
            $this->paramsData($params, $notifiable, $groupon, $grouponLogs, $grouponLeader, $goods);
        }

        return $this->formatParams($params, $type);
    }


    public function toWxMiniProgram($notifiable) {
        $event = $this->event;
        $data = $this->data;
        $groupon = $data['groupon'];
        $grouponLogs = $data['grouponLogs'];
        $grouponLeader = $data['grouponLeader'];
        $goods = $data['goods'];

        $params = [];

        if ($oauth = $this->getWxOauth($notifiable, 'wxMiniProgram')) {
            // 拼团详情
            $path = "/pages/activity/groupon/detail?id=" . $groupon['id'];

            // 获取小程序完整路径
            $path = $this->getMiniDomainUrl($path);

            $params['openid'] = $oauth->openid;
            $params['page'] = $path;

            // 获取消息data
            $this->paramsData($params, $notifiable, $groupon, $grouponLogs, $grouponLeader, $goods);
        }

        return $this->formatParams($params, 'wxMiniProgram');
    }



    private function paramsData(&$params, $notifiable, $groupon, $grouponLogs, $grouponLeader, $goods) {
        $params['data'] = [];
        switch ($this->event) {
            case 'groupon_success':
                $params['data'] = array_merge($params['data'], $this->grouponSuccessData($notifiable, $groupon, $grouponLogs, $grouponLeader, $goods));
                break;
            case 'groupon_fail':
                $params['data'] = array_merge($params['data'], $this->grouponFailData($notifiable, $groupon, $grouponLogs, $grouponLeader, $goods));
                break;
            default:
                $params = [];
        }
    }


    private function grouponSuccessData($notifiable, $groupon, $grouponLogs, $grouponLeader, $goods) {
        // 当前订单
        $order = $this->getCurrentOrder($notifiable, $groupon, $grouponLogs);
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['goods_title'] = $goods['title'];
        $data['groupon_user'] = $notifiable['nickname'];
        $data['groupon_leader'] = $grouponLeader ? $grouponLeader['nickname'] : '';
        $data['groupon_price'] = $order ? '￥' . $order['goods_amount'] : '';
        $data['groupon_start_time'] = date('Y-m-d H:i:s', $groupon['createtime']);
        $data['groupon_finish_time'] = date('Y-m-d H:i:s', $groupon['finishtime']);
        $data['groupon_num'] = $groupon['num'];
        $data['order_sn'] = $order ? $order['order_sn'] : '';

        return $data;
    }


    private function grouponFailData($notifiable, $groupon, $grouponLogs, $grouponLeader, $goods) {
        // 当前订单
        $order = $this->getCurrentOrder($notifiable, $groupon, $grouponLogs);
        $data['template'] = self::$returnField[$this->event]['name'];           // 模板名称
        $data['goods_title'] = $goods['title'];
        $data['groupon_user'] = $notifiable['nickname'];
        $data['groupon_leader'] = $grouponLeader ? $grouponLeader['nickname'] : '';
        $data['groupon_price'] = $order ? '￥' . $order['goods_amount'] : '';
        $data['groupon_start_time'] = date('Y-m-d H:i:s', $groupon['createtime']);
        $data['groupon_current_num'] = $groupon['current_num'];
        $data['groupon_num'] = $groupon['num'];
        $data['order_sn'] = $order ? $order['order_sn'] : '';
        $data['refund_money'] = $order ? '￥' . $order['pay_fee'] : '';

        return $data;
    }


    // 获取当前订单
    private function getCurrentOrder($notifiable, $groupon, $grouponLogs) {
        foreach ($grouponLogs as $key => $log) {
            if ($log['user_id'] == $notifiable['id']) {
                if ($log['order_id']) {
                    $order = OrderModel::where('id', $log['order_id'])->find();
                }
                
                break;
            }
        }

        return $order ?? null;
    }
}
