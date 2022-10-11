<?php

namespace addons\shopro\library\notify;

use addons\shopro\exception\Exception;
use think\queue\ShouldQueue;

class Notify
{
    
    public function sendNotify($notifiables, $notification) {
        if ($notification instanceof ShouldQueue) {
            // 队列执行
            return $this->sendQueueNotify($notifiables, $notification, $notification->delay);
        } 

        return $this->sendNowNotify($notifiables, $notification);
    }



    /**
     * 立即发送
     */
    public function sendNowNotify($notifiables, $notification) {
        foreach ($notifiables as $key => $notifiable) {
            $channels = $notification->via($notifiable);

            if (empty($channels)) {
                continue;
            }

            foreach ($channels as $k => $channel) {
                (new $channel)->send($notifiable, $notification);
            }
        }
    }


    /**
     * 队列发送
     * delay 延迟时间
     */
    public function sendQueueNotify($notifiables, $notification, $delay) {
        if ($delay > 0) {
            // 异步延迟发送
            \think\Queue::later($delay, '\addons\shopro\job\Notification@send', [
                'notifiables' => $notifiables, 
                'notification' => $notification,
                'notification_name' => get_class($notification)
            ], 'shopro');
        } else {
            // 异步立即发送
            \think\Queue::push('\addons\shopro\job\Notification@send', [
                'notifiables' => $notifiables,
                'notification' => $notification,
                'notification_name' => get_class($notification)
            ], 'shopro');
        }
    }



    public static function __callStatic($name, $arguments)
    {
        return (new self)->{$name . 'Notify'}(...$arguments);
    }
}