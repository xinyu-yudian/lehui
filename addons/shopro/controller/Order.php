<?php

namespace addons\shopro\controller;


use app\admin\model\shopro\Systemconfig;
use app\admin\model\shopro\work;
use think\Config;

class Order extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();

        $this->success('订单列表', \addons\shopro\model\Order::getList($params));
    }



    public function detail()
    {
        $params = $this->request->get();
        $this->success('订单详情', \addons\shopro\model\Order::detail($params));
    }


    public function itemDetail()
    {
        $params = $this->request->get();
        $this->success('订单商品', \addons\shopro\model\Order::itemDetail($params));
    }


    // 即将废弃
    public function statusNum()
    {
        $this->success('订单数量', \addons\shopro\model\Order::statusNum());
    }


    // 取消订单
    public function cancel()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'cancel');

        $this->success('取消成功', \addons\shopro\model\Order::operCancel($params));
    }

    // 删除订单
    public function delete()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'delete');

        $this->success('删除成功', \addons\shopro\model\Order::operDelete($params));
    }

    // 确认收货
    public function confirm()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'confirm');

        $this->success('收货成功', \addons\shopro\model\Order::operConfirm($params));
    }


    public function comment()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'comment');

        $this->success('评价成功', \addons\shopro\model\Order::operComment($params));
    }


    public function pre()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'pre');

        $result = \addons\shopro\model\Order::pre($params);

        if (isset($result['msg']) && $result['msg']) {
            $this->error($result['msg'], $result);
        } else {
            // 获取厨师上门的最低消费
            $config = Systemconfig::where('name', '=', 'cook_money')->find();
            if($config){
                $cook_money = $config['value'];
            } else {
                $cook_money = 1000;
            }
            $result['cook_money'] = $cook_money;
            $this->success('计算成功', $result);
        }
    }
    public function time()
    {

        $result = \addons\shopro\model\Work::getTimeList();
        // $day= date("Y-m-d");
        // $tomorrow  = date("Y-m-d",strtotime("+1 day"));
        // $last  = date("Y-m-d",strtotime("+2 day"));
        $timeArr['goods'] = [];
        $timeArr['master'] = [];
        foreach ($result as $key => $value) {
            if($value['status'] == 'normal'){
                // $day_time = $day.' '.$value['work'];
                // $tomorrow_time = $tomorrow.' '.$value['work'];
                // $last_time = $last.' '.$value['work'];
                // $timeArr[] = $day.' '.$value['work'];
                // $timeArr[] = $tomorrow.' '.$value['work'];
                // $timeArr[] = $last.' '.$value['work'];
                if($value['cate'] == 'goods'){
                    $timeArr['goods'][] = $value['work'];
                }else if ($value['cate'] == 'master') {
                    $timeArr['master'][] = $value['work'];
                }

            }
        }
        $this->success('营业时间', $timeArr);

    }

    public function createOrder()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'createOrder');

        if(isset($params['goods_list']) && $params['goods_list']){
            if(is_array($params['goods_list']) && count($params['goods_list'])>0){
                $timeArr = explode(' ', $params['goods_list'][0]['dispatch_date']);
                $order_where['service'] = $params['goods_list'][0]['dispatch_date'];
                $work = \addons\shopro\model\Work::where('work',$timeArr[1])->find();
                $count = \addons\shopro\model\Order::where($order_where)->count();
                if($work['num']<$count){
                    $this->error('订单添加失败,当前服务时间段内订单数已达到最大数量，请选择其他时间');
                }
            }
        }
        $order = \addons\shopro\model\Order::createOrder($params);
        $this->success('订单添加成功', $order);
    }


    // 获取可用优惠券列表
    public function coupons()
    {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'coupons');

        $coupons = \addons\shopro\model\Order::coupons($params);

        $this->success('获取成功', $coupons);
    }
}
