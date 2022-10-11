<?php

namespace addons\shopro\controller;


class Coupons extends Base
{

    protected $noNeedLogin = ['lists', 'detail', 'goods'];
    protected $noNeedRight = ['*'];

    // 领券中心，自己的优惠券
    public function index()
    {
        $type = $this->request->get('type');
        $this->success('优惠券中心', \addons\shopro\model\Coupons::getCouponsList($type));
    }

    public function lists()
    {
        $ids = $this->request->get('ids');
        $this->success('优惠券列表', \addons\shopro\model\Coupons::getCouponsListByIds($ids));

    }

    public function get()
    {
        $id = $this->request->get('id');
        $this->success('领取成功', \addons\shopro\model\Coupons::getCoupon($id));
    }

    public function detail()
    {
        $id = $this->request->get('id');
        $user_coupons_id = $this->request->get('user_coupons_id', 0);
        $detail = \addons\shopro\model\Coupons::getCouponsDetail($id, $user_coupons_id);

        $this->success('优惠券详情', $detail);
    }

    public function goods()
    {
        $id = $this->request->get('id');
        $detail = \addons\shopro\model\Coupons::getGoodsByCoupons($id);

        $this->success('优惠券详情', $detail);
    }




}
