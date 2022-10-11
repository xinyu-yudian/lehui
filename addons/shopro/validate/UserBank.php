<?php

namespace addons\shopro\validate;

use think\Validate;

class UserBank extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'real_name' => 'require',
        'bank_name' => 'require',
        'card_no' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'type.require' => '请选择您的提现账户类型',
        'real_name.require' => '真实姓名必须填写',
        'bank_name.require' => '开户行必须填写',
        'card_no.require' => '账号必须填写',
    ];

    /**
     * 字段描述
     */
    protected $field = [
        
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'edit' => ['type', 'real_name', 'bank_name', 'card_no'],
    ];

}
