<?php

namespace addons\shopro\job;

use think\queue\Job;

/**
 * 队列消息通知
 */
class Notification extends BaseJob
{
    /**
     * 发送通知
     */
    public function send(Job $job, $data){
        try {
            // 这里获取到的 $notifiables 和 notification 两个都是数组，不是类，尴尬， 更可恨的 notification 只是 {"delay":0,"event":"changemobile"}
            $notifiables = $data['notifiables'];
            $notification = $data['notification'];
            // 因为 notification 只有参数，需要把对应的类传过来，在这里重新初始化
            $notification_name = $data['notification_name'];

            // 重新实例化 notification 实例
            if (class_exists($notification_name)) {
                $notification = new $notification_name($notification['data']);

                // 发送消息
                \addons\shopro\library\notify\Notify::sendNow($notifiables, $notification);
            }
            
            // 删除 job
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::error('queue-' . get_class()
                . (isset($notification->event) ? ('-' . $notification->event) : '') 
                . '：执行失败，错误信息：' . $e->getMessage());
        }
    }
}