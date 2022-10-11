<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use addons\shopro\model\UserAddress;
use addons\shopro\model\Area;

class Address extends Base
{

    protected $noNeedLogin = ['area'];
    protected $noNeedRight = ['*'];

    public function index()
    {
        $this->success('收货地址', UserAddress::getUserAddress());
    }

    public function defaults()
    {
        $this->success('默认收货地址', UserAddress::getUserDefaultAddress());
    }

    public function area()
    {
        $data['provinceData'] = Area::where('level', 1)->order('id asc')->field('id as value, name as label, pid, level')->select();
        foreach ($data['provinceData'] as $k => $p) {
            $data['cityData'][$k] = Area::where(['level' => 2, 'pid' => $p->value])->order('id asc')->field('id as value, name as label, pid, level')->select();
            foreach ($data['cityData'][$k] as $i => $c) {
                $data['areaData'][$k][$i] = Area::where(['level' => 3, 'pid' => $c->value])->order('id asc')->field('id as value, name as label, pid, level')->select();
            }
        }

        $this->success('省市区', $data);

    }

    public function edit()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'edit');

        $this->success('编辑地址', UserAddress::edit($params));
    }

    public function info()
    {
        $params = $this->request->get();
        $this->success('地址详情', UserAddress::info($params));
    }

    public function del()
    {
        $params = $this->request->post();
        $this->success('地址详情', UserAddress::del($params));

    }


}