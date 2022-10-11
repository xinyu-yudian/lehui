<?php

namespace addons\shopro\library\notify\channel;

use addons\shopro\notifications\Notification;
use app\common\library\Sms as Smslib;

class Sms
{

    public function __construct()
    {
    }


    /**
     * 发送 模板消息
     *
     * @param  mixed  $notifiable       // 通知用户
     * @param  通知内容
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $data = [];

        if (method_exists($notification, 'toSms')) {
            $data = $notification->toSms($notifiable);

            if ($data && $data['phone'] && isset($data['template_id'])) {
                $mobile = $data['phone'];
                $sendData = $data['data'] ?? [];

                $params = [
                    'mobile'   => $mobile,
                    'msg'      => $sendData,
                    'template' => $data['template_id']
                ];
                $result = \think\Hook::listen('sms_notice', $params, null, true);

                if (!$result) {
                    // 短信发送失败
                    \think\Log::write('短信发送失败：用户：'. $notifiable['id'] . '；类型：' . get_class($notification) . "；发送类型：" . $notification->event);
                } else {
                    // 发送成功
                    $notification->sendOk('sms');
                }

                return true;
            }
            // 没有手机号
            \think\Log::write('短信发送失败，没有手机号：用户：' . $notifiable['id'] . '；类型：' . get_class($notification) . "；发送类型：" . $notification->event);
        }

        return true;
    }
}
