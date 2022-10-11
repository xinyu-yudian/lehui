<?php

namespace addons\shopro\listener\activity;

use addons\shopro\exception\Exception;
use addons\shopro\model\User;
use addons\shopro\library\traits\ActivityCache;
use addons\shopro\model\Activity;

/**
* 活动更新
*/
class Update
{
    use ActivityCache;


    // 活动创建后
    public function activityUpdateAfter(&$params)
    {
        $activity = $params['activity'];
        
        // 重新查询活动
        $activity = Activity::where('id', $activity['id'])->find();

        if ($this->hasRedis()) {
            // 如果存在 redis 将活动存入缓存
            $activitySkuPrice = [];
            if (in_array($activity['type'], ['seckill', 'groupon'])) {
                $activitySkuPrice = $activity['activity_goods_sku_price'];
            }
            $this->setActivity($activity, $activitySkuPrice);
        }

        // 添加活动自动关闭的队列
        $rules = $activity['rules'];

        $laterTime = $activity['endtime'];
        if (isset($rules['activity_auto_close']) && $rules['activity_auto_close'] > 0) {
            $laterTime += ($rules['activity_auto_close'] * 60);
        }

        // 活动结束后再加上设置的自动结束时间 自动删除活动
        \think\Queue::later(($laterTime - time()), '\addons\shopro\job\ActivityAutoOper@autoClose', ['activity' => $activity], 'shopro');
    }


    public function activityDeleteAfter(&$params) {
        $activity = $params['activity'];

        if ($this->hasRedis()) {
            $this->delActivity($activity);
        }
    }
    
}
