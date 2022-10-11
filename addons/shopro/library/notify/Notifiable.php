<?php

namespace addons\shopro\library\notify;

use addons\shopro\exception\Exception;
use think\queue\ShouldQueue;
/**
 * 消息通知 trait
 */

trait Notifiable
{
    public function notify ($notification) {
        return \addons\shopro\library\notify\Notify::send([$this], $notification);
    }
    
}
