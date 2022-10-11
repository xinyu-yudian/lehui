<?php

namespace addons\shopro\listener\order;

use addons\shopro\exception\Exception;
use addons\shopro\model\Cart;
use addons\shopro\model\Config;
use addons\shopro\model\Order;
use addons\shopro\model\User;

/**
 * 售后行为
 */
class Aftersale
{

    // 售后单发生变动
    public function aftersaleChange(&$params) {
        $aftersale = $params['aftersale'];
        $order = $params['order'];
        $aftersaleLog = $params['aftersaleLog'];

        // 通知用户售后处理过程
        $user = User::where('id', $aftersale['user_id'])->find();
        $user && $user->notify(
            new \addons\shopro\notifications\Aftersale([
                'aftersale' => $aftersale,
                'order' => $order,
                'aftersaleLog' => $aftersaleLog,
                'event' => 'aftersale_change'
            ])
        );
        
    }

}
