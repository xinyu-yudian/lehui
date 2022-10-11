<?php

namespace addons\shopro\controller;

use addons\shopro\model\Share as ShareModel;

class Share extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    /**
     * 获取分享记录
     *
     * @return void
     */
    public function index()
    {
        $params = $this->request->get();

        $shares = ShareModel::getList($params);
        return $this->success('获取成功', $shares);
    }


    public function add()
    {

        $spm = $this->request->post('spm');
        $share = false;
        if (!empty($spm)) {
            $share = \think\Db::transaction(function () use ($spm) {
                try {
                    $shareLog = ShareModel::add($spm);
                    if ($shareLog) {
                        \think\Hook::listen('share_after', $shareLog);
                        return true;
                    }
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
                return false;
            });
        }
        if($share) {
            $this->success('识别成功'); // 使用 success 前端不提示
        }
    }
}
