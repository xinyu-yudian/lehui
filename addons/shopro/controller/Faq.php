<?php

namespace addons\shopro\controller;


class Faq extends Base
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    // faq 列表
    public function index()
    {
        $this->success('获取成功', \addons\shopro\model\Faq::order('id', 'DESC')->paginate(10));
    }


    public function detail () {
        $id = $this->request->get('id');

        $this->success('签到成功', \addons\shopro\model\Faq::where('id', $id)->find());
    }

}
