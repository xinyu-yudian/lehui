<?php

namespace addons\shopro\validate;

use think\Validate;

class Order extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'id' => 'require',
        'order_item_id' => 'require',
        
        'level' => 'require|number|between:1,5',
        'content' => 'require',
        'images' => 'array',

        'goods_list' => 'require|array|min:1'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'id.require' => '缺少参数',
        'order_item_id.require' => '缺少订单商品参数',

        // 评价
        'level.require' => '描述相符必须选择',
        'level.number' => '描述相符必须选择',
        'level.between' => '描述相符必须选择',
        'content.require' => '评价内容必须填写',
        'images.array' => '图片不正确',

        // 添加订单
        "goods_list" => '请选择要购买的商品',
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
        'cancel' => ['id'],
        'delete' => ['id'],
        'confirm' => ['id', 'order_item_id'],
        'aftersale' => ['id', 'order_item_id'],
        'refund' => ['id', 'order_item_id'],
        'comment' => ['id', 'order_item_id', 'level', 'content', 'images'],
        'pre' => ['goods_list'],
        'createOrder' => ['goods_list'],
        'coupons' => ['goods_list']
    ];

}
