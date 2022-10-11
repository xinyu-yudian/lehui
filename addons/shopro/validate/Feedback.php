<?php

namespace addons\shopro\validate;

use think\Validate;

class Feedback extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'type' => 'require',
        'content' => 'require',
        'images' => 'array',
        'phone' => 'regex:^1\d{10}$'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'type.require' => '反馈类型必须选择',
        'content.require' => '反馈内容必须填写',
        'images.array' => '图片不正确',
        'phone.regex' => '手机号格式不正确',
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
        'add' => ['type', 'content', 'images', 'phone']
    ];

}
