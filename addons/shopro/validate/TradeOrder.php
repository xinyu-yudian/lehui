<?php

namespace addons\shopro\validate;

use think\Validate;

class TradeOrder extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'recharge_money' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'recharge_money.require' => '请输入充值金额',
    ];

    /**
     * 字段描述
     */
    protected $field = [];

    /**
     * 验证场景
     */
    protected $scene = [
        'recharge' => ['recharge_money'],
    ];
}
