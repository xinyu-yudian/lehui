<?php

namespace addons\shopro\controller\store;

class Order extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();

        $this->success('订单列表', \addons\shopro\model\store\Order::getList($params));
    }



    public function detail()
    {
        $params = $this->request->get();
        $this->success('订单详情', \addons\shopro\model\store\Order::detail($params));
    }


    public function send() {
        $params = $this->request->post();
        $this->success('发货成功', \addons\shopro\model\store\Order::operSend($params));
    }


    public function confirm()
    {
        $params = $this->request->post();
        $this->success('核销成功', \addons\shopro\model\store\Order::operConfirm($params));
    }
}
