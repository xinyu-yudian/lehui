<?php

namespace addons\shopro\controller;


class Feedback extends Base
{

    protected $noNeedLogin = ['type'];
    protected $noNeedRight = ['*'];


    public function type()
    {
        $this->success('反馈类型', array_values(\addons\shopro\model\Feedback::$typeAll));
    }


    public function add() {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'add');

        $this->success('反馈成功', \addons\shopro\model\Feedback::add($params));
    }

}
