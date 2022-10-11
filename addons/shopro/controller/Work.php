<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
// use addons\shopro\model\UserStore;

class Work extends Base
{

    protected $noNeedLogin = ['index', 'detail', 'lists', 'activity'];
    protected $noNeedRight = ['*'];

    public function index()
    {
        $this->success('营业时间', \addons\shopro\model\Work::getTimeList());
    }
}
