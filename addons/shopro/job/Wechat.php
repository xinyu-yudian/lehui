<?php

namespace addons\shopro\job;

use think\queue\Job;

/**
 * 微信任务
 */
class Wechat extends BaseJob
{
    /**
     * 异步分批创建新的队列任务
     */
    public function createQueueByOpenIdsArray(Job $job, $openIdsArray)
    {
        try {
            $count = count($openIdsArray);
            if ($count > 0) {
                $page = ceil($count / 100);
                for ($i = 0; $i < $page; $i++) {
                    \think\Queue::push('\addons\shopro\job\Wechat@saveSubscribeUserInfo', array_slice($openIdsArray, $i * 100, 100), 'shopro');
                }
            }
            // 删除 job
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::error('queue-' . get_class() . '-createQueueByOpenIdsArray' . '：执行失败，错误信息：' . $e->getMessage());
        }
    }

    /**
     * 保存更新关注用户
     */
    public function saveSubscribeUserInfo(Job $job, $openIdsArray)
    {
        try {
            $wechat = new \addons\shopro\library\Wechat('wxOfficialAccount');
            $result = $wechat->getSubscribeUserInfoByOpenId($openIdsArray);

            if (isset($result['user_info_list'])) {
                $userInfoList = $result['user_info_list'];
                $insertData = [];
                foreach ($userInfoList as $u) {
                    $wechatFans = \app\admin\model\shopro\wechat\Fans::get(['openid' => $u['openid']]);
                    if ($wechatFans) {
                        $wechatFans->save([
                            'nickname' => $u['nickname'],
                            'headimgurl' => $u['headimgurl'],
                            'sex' => $u['sex'],
                            'country' => $u['country'],
                            'province' => $u['province'],
                            'city' => $u['city'],
                            'subscribe' => 1,
                            'subscribe_time' => $u['subscribe_time']
                        ]);
                    }else{
                        $insertData[] = $u;
                    }
                }
                if (count($insertData) > 0) {
                    $wechatFans = new \app\admin\model\shopro\wechat\Fans;
                    $wechatFans->allowField(true)->saveAll($insertData);
                }

            }
            $job->delete();
        } catch (\Exception $e) {
            // 队列执行失败
            \think\Log::error('queue-' . get_class() . '-saveSubscribeUserInfo' . '：执行失败，错误信息2：' . $e->getMessage() . '|' . json_encode($insertData, JSON_UNESCAPED_UNICODE));
        }
    }
}