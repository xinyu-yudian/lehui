<?php

namespace addons\shopro\controller;


class OrderExpress extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();

        $this->success('包裹列表', \addons\shopro\model\OrderExpress::getList($params));
    }


    public function detail()
    {
        $params = $this->request->get();

        $this->success('包裹详情', \addons\shopro\model\OrderExpress::detail($params));
    }
}
