<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use addons\shopro\model\NotificationConfig;
use think\Cache;


class Notification extends Base
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    public function template()
    {
        $platform = request()->header('platform');

        $templates = [];
        if (in_array($platform, ['wxMiniProgram', 'wxOfficialAccount'])) {
            $platform = $platform == 'wxOfficialAccount' ? 'wxOfficialAccountBizsend' : $platform;
            // 获取订阅消息模板
            $notificationConfig = NotificationConfig::cache(300)->where('platform', $platform)->select();
            
            foreach ($notificationConfig as $k => $config) {
                if ($config['status'] && $config['content_arr'] && $config['content_arr']['template_id']) {
                    $templates[$config['event']] = $config['content_arr']['template_id'];
                }
            }
        }

        $this->success('获取成功', $templates);
    }


}
