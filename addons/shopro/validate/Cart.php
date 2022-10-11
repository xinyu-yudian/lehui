<?php

namespace addons\shopro\validate;

use think\Validate;

class Cart extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'goods_list' => 'require|array',
        'cart_list' => 'require|array',
        'act' => 'require'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'goods_list.require' => '请选择商品',
        'goods_list.array' => '请选择商品',
        'cart_list.require' => '请选择购物车商品',
        'cart_list.array' => '请选择购物车商品',
        'act.require' => '参数错误',
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
        'add' => ['goods_list'],
        'edit' => ['cart_list', 'act'],
    ];

}
