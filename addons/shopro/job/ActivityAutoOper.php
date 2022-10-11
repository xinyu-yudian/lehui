<?php

namespace addons\shopro\job;

use addons\shopro\library\traits\ActivityCache;
use addons\shopro\model\Activity;
use think\queue\Job;


/**
 * 订单自动操作
 */
class ActivityAutoOper extends BaseJob
{
    use ActivityCache;

    /**
     * 活动自动删除
     */
    public function autoClose(Job $job, $data){
        try {
            $activity = $data['activity'];

            $activity = Activity::get($activity['id']);

            // 一个活动会存在多个队列，要排重
            if ($activity) {
                // 如果活动还没被删除

                // 规则
                $rules = $activity['rules'];

                // 当前配置应该自动结束的时间
                $laterTime = $activity['endtime'];
                if (isset($rules['activity_auto_close']) && $rules['activity_auto_close'] > 0) {
                    $laterTime += ($rules['activity_auto_close'] * 60);
                }

                // 如果当前时间大于 laterTime，可以执行删除
                if (time() >= $laterTime) {
                    // 删除活动 【软删】
                    $activity->delete();

                    // 删除活动缓存
                    $this->delActivity($activity);
                }
            }

            // 删除 job
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::write('queue-' . get_class() . '-autoClose' . '：执行失败，错误信息：' . $e->getMessage());
        }
    }
    
}