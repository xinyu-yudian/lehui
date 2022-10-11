<?php

namespace addons\shopro\job;

use addons\shopro\model\Order;
use addons\shopro\model\OrderAction;
use think\queue\Job;


/**
 * BaseJob 基类
 */
class BaseJob
{

    public function failed($data){
        // 记录日志
        \think\Db::name('shopro_failed_job')->insert([
            'data' => json_encode($data),
            'createtime' => time(),
            'updatetime' => time()
        ]);
    }

}