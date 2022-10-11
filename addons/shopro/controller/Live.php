<?php

namespace addons\shopro\controller;

use addons\shopro\library\Wechat;
use addons\shopro\exception\Exception;
use think\Cache;

class Live extends Base
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();
        
        $type = $params['type'] ?? 'all';
        $ids = array_filter(isset($params['ids']) ? explode(',', $params['ids']) : []);

        if (!in_array($type, ['all', 'notice', 'living', 'lived'])) {
            $this->error('参数错误');
        }

        // 同步直播
        \addons\shopro\model\Live::autoSyncLive();
        
        if ($type != 'all') {
            $lives = \addons\shopro\model\Live::{$type}()
                        ->with('goods')
                        ->order('id', 'desc')
                        ->paginate(10);
        } else {
            $lives = \addons\shopro\model\Live::order('live_status', 'asc')
                        ->with('goods')
                        ->order('id', 'desc');

            if (isset($params['ids'])) {
                // 首页根据 id 获取
                $lives = $lives->where('id', 'in', $ids)->select();
            } else {
                // 直播列表
                $lives = $lives->paginate(10);
            }
        }

        $this->success('获取成功', $lives);
    }


}
