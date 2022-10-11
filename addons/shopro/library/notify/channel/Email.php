<?php

namespace addons\shopro\library\notify\channel;

use addons\shopro\notifications\Notification;
use think\Validate;
use app\common\library\Email as SendEmail;

class Email
{

    public function __construct()
    {
    }


    /**
     * 发送 微信模板消息
     *
     * @param  mixed  $notifiable       // 通知用户
     * @param  通知内容
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $data = [];

        if (method_exists($notification, 'toEmail')) {
            $data = $notification->toEmail($notifiable);

            if ($data && isset($notifiable['email']) && Validate::is($notifiable['email'], "email")) {
                $email = new SendEmail;
                $result = $email
                    ->to($notifiable['email'], $notifiable['nickname'])
                    ->subject(($data['data'] ? $data['data']['template'] : '邮件通知'))
                    ->message('<div style="min-height:550px; padding: 50px 20px 100px;">' . $data['content'] . '</div>')
                    ->send();
                if ($result) {
                    // 发送成功
                    $notification->sendOk('email');
                } else {
                    // 邮件发送失败
                    \think\Log::write('邮件消息发送失败：用户：' . $notifiable['id'] . '；类型：' . get_class($notification) . "；发送类型：" . $notification->event . "；错误信息：" . json_encode($email->getError()));
                }

                return true;
            }

            // 没有openid
            \think\Log::write('邮件消息发送失败，没有 email，或 email 格式不正确：用户：' . $notifiable['id'] . '；类型：' . get_class($notification) . "；发送类型：" . $notification->event);
        }

        return true;
    }
}
