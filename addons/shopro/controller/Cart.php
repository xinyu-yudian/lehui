<?php

namespace addons\shopro\controller;

use addons\shopro\model\Cart as CartModel;

class Cart extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    public function index()
    {
        $data = CartModel::info();
        $this->success('我的购物车', $data);
    }

    public function add()
    {
        $params = $this->request->post();
        
        // 表单验证
        $this->shoproValidate($params, get_class(), 'add');

        $goodsList = $params['goods_list'];
        $this->success('已添加', CartModel::add($goodsList));
    }

    public function edit()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'edit');

        $this->success('', CartModel::edit($params));
    }

}