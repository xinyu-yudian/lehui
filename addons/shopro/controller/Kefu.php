<?php

namespace addons\shopro\controller;

/**
 * 客服接口
 */
class Kefu extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    /**
     * 对接Fastadmin插件市场WorkerMan客服插件
     */
    public function historyGoods()
    {
        $viewList = \addons\shopro\model\UserView::getGoodsList();
        if(!empty($viewList)) {
            foreach($viewList as $k => &$v) {
                $v->id = $v->goods_id;
                $v->logo = $v->goods->image;
                $v->subject = $v->goods->title;
                $v->note = $v->goods->subtitle;
                $v->price = $v->goods->price;

                unset($v['goods']);
                unset($v['goods_id']);
                unset($v['user_id']);
            }
        }
        $this->success('浏览历史', $viewList);
    }

    public function historyOrder()
    {
        $orderList = \addons\shopro\model\Order::getList(['type' => 'all']);
        if(!empty($orderList)) {
            foreach($orderList['data'] as $k => &$v) {
                $order = [];
                $order['id'] = $v['id'];
                $order['subject'] = $v['item'][0]['goods_title'];
                $order['logo'] = $v['item'][0]['goods_image'];
                $order['note'] = "订单编号:{$v['order_sn']}";
                $order['price'] = $v['total_amount'];
                $order['number'] = 23;
                $itemNumber = count($v['item']);

                if($itemNumber == 1) {
                    $order['subject'] = "{$order['subject']}...等{$itemNumber}个产品";
                }
            
                $v = $order;
            }
        }
        $this->success('order', $orderList);
    }


}