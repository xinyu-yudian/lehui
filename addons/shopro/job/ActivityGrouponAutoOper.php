<?php

namespace addons\shopro\job;

use addons\shopro\library\traits\ActivityCache;
use addons\shopro\library\traits\Groupon;
use addons\shopro\model\Activity;
use addons\shopro\model\ActivityGroupon;
use think\queue\Job;


/**
 * 订单自动操作
 */
class ActivityGrouponAutoOper extends BaseJob
{
    use ActivityCache, Groupon;

    /**
     * 拼团判断，将团结束, 
     */
    public function expire(Job $job, $data){
        try {
            $activity = $data['activity'];
            $activity_groupon_id = $data['activity_groupon_id'];
            $activityGroupon = ActivityGroupon::where('id', $activity_groupon_id)->find();

            // 活动正在进行中， 走这里的说明人数 都没满
            if ($activityGroupon && $activityGroupon['status'] == 'ing') {
                \think\Db::transaction(function () use ($activity, $activityGroupon) {
                    $rules = $activity['rules'];
                    // 是否虚拟成团
                    $is_fictitious = $rules['is_fictitious'] ?? 0;
                    // 最大虚拟人数 ,不填或者 "" 不限制人数，都允许虚拟成团， 0相当于不允许虚拟成团
                    $fictitious_num = (!isset($rules['fictitious_num']) || $rules['fictitious_num'] == '') ? 'no-limit' : $rules['fictitious_num'];
                    // 拼团剩余人数
                    $surplus_num = $activityGroupon['num'] - $activityGroupon['current_num'];

                    if ($is_fictitious && ($fictitious_num == 'no-limit' || $fictitious_num >= $surplus_num)) {
                        // 虚拟成团，如果虚拟用户不够，则自动解散
                        $this->finishFictitiousGroupon($activityGroupon);
                    } else {
                        // 解散退款
                        $this->invalidRefundGroupon($activityGroupon);
                    }
                });
            }

            // 删除 job
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::write('queue-' . get_class() . '-expire' . '：执行失败，错误信息：' . $e->getMessage());
        }
    }
    
}