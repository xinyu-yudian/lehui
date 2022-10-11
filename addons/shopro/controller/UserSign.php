<?php

namespace addons\shopro\controller;


class UserSign extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    // 按月份获取签到记录
    public function index()
    {
        $params = $this->request->get();
        
        $this->success('获取成功', \addons\shopro\model\UserSign::getList($params));
    }


    public function sign () {
        $params = $this->request->post();

        $this->success('签到成功', \addons\shopro\model\UserSign::sign($params));
    }

}
