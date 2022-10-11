<?php

namespace addons\shopro\controller;


class GoodsComment extends Base
{

    protected $noNeedLogin = ['index', 'type'];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();
        
        $goodsComments = \addons\shopro\model\GoodsComment::getList($params);
        
        $this->success('评价详情', $goodsComments);
    }


    public function type() {
        $goods_id = $this->request->get('goods_id', 0);

        $type = array_values(\addons\shopro\model\GoodsComment::$typeAll);

        foreach ($type as $key => $val) {
            // 只查询 count 比查出来所有评论，在判断状态要快
            $comment = \addons\shopro\model\GoodsComment::where('goods_id', $goods_id);
            if ($val['code'] != 'all') {
                $comment = $comment->{$val['code']}();
            }
            $comment = $comment->count();
            $type[$key]['num'] = $comment;
        }

        $this->success('筛选类型', $type);
    }
}
