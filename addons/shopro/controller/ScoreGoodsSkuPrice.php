<?php

namespace addons\shopro\controller;


class ScoreGoodsSkuPrice extends Base
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function index()
    {
        $params = $this->request->get();
        $goods = \addons\shopro\model\ScoreGoodsSkuPrice::getGoodsList($params);
        
        $this->success('积分商城列表', $goods);
    }

    public function detail()
    {
        $id = $this->request->get('id');
        $detail = \addons\shopro\model\ScoreGoodsSkuPrice::getGoodsDetail($id);

        $this->success('商品详情', $detail);
    }
}
