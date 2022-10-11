<?php

namespace addons\shopro\library\notify\channel;

use addons\shopro\notifications\Notification;
use addons\shopro\model\Notification as NotificationModel;

class Database
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

        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($notifiable);

            $notificationModel = new NotificationModel();
            $notificationModel->type = $notification->event;
            $notificationModel->notifiable_id = $notifiable['id'];
            $notificationModel->notifiable_type = $notification->notifiableType;
            $notificationModel->data = json_encode($data);

            $notificationModel->save();
        }
        
        return true;
    }
}
