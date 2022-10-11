<?php

namespace addons\shopro\validate;

use think\Validate;

class Decorate extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'id' => 'require',
        'image' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'id.require' => '缺少参数',
        'image.require' => '缺少图片地址',
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
        'saveImg' => ['id', 'image'],
    ];

}
