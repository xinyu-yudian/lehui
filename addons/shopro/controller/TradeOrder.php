<?php

namespace addons\shopro\controller;


class TradeOrder extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();

        $this->success('充值记录', \addons\shopro\model\TradeOrder::getList($params));
    }

    public function detail()
    {
        $params = $this->request->get();
        $this->success('订单详情', \addons\shopro\model\TradeOrder::detail($params));
    }


    public function recharge()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'recharge');
		
        $order = \addons\shopro\model\TradeOrder::recharge($params);
		
        $this->success('订单添加成功', $order);
    }
}
