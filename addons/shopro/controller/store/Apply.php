<?php

namespace addons\shopro\controller\store;

use addons\shopro\exception\Exception;
use addons\shopro\controller\Base as ShoproBase;

/**
 * 不继承门店的 base
 */
class Apply extends ShoproBase
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function info() {
        $this->success('门店申请', \addons\shopro\model\store\Apply::info());
    }


    public function apply() {
        $params = $this->request->post();

        // 表单验证
        $this->shoproValidate($params, get_class(), 'apply');

        $order = \addons\shopro\model\store\Apply::apply($params);

        $this->success('门店申请提交成功', $order);
    }

}
