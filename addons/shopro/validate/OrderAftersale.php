<?php

namespace addons\shopro\validate;

use think\Validate;

class OrderAftersale extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'id' => 'require',
        'order_id' => 'require',
        'order_item_id' => 'require',

        'type' => 'require',
        'phone' => 'require',
        'reason' => 'require',
        'images' => 'array',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'id.require' => '缺少参数',
        
        'type.require' => '请选择售后类型',
        'order_id.require' => '缺少参数',
        'order_item_id.require' => '缺少订单商品参数',
        'phone.require' => '联系方式必须填写',
        'reason.require' => '售后原因必须选择',
        'images.array' => '图片不正确',
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
        'detail' => ['id'],
        'cancel' => ['id'],
        'delete' => ['id'],
        'aftersale' => ['type', 'order_id', 'order_item_id', 'phone', 'reason', 'images'],
    ];

}
