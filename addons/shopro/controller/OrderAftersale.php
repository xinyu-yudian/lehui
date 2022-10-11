<?php

namespace addons\shopro\controller;


class OrderAftersale extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();

        $this->success('售后列表', \addons\shopro\model\OrderAftersale::getList($params));
    }


    /**
     * 详情
     */
    public function detail()
    {
        $params = $this->request->get();

        $this->shoproValidate($params, get_class(), 'detail');

        $this->success('售后详情', \addons\shopro\model\OrderAftersale::detail($params));
    }



    // 申请售后
    public function aftersale()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'aftersale');

        $this->success('申请成功', \addons\shopro\model\OrderAftersale::aftersale($params));
    }


    // 取消售后单
    public function cancel()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'cancel');

        $this->success('取消成功', \addons\shopro\model\OrderAftersale::operCancel($params));
    }

    // 删除售后单
    public function delete()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'delete');

        $this->success('删除成功', \addons\shopro\model\OrderAftersale::operDelete($params));
    }

}
