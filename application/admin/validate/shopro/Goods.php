<?php

namespace app\admin\validate\shopro;

use think\Validate;

class Goods extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'title' => 'require',  // 商品标题：必填
        'category_ids' => 'require',
        'image' => 'require',
        'images' => 'require',
        'price' => 'require',
        'original_price' => 'require',
        'dispatch_type' => 'require',   // 发货方式：必填
        'dispatch_ids' => 'require',   // 发货模板
        // 'service_ids' => 'require', // 商品服务
    ];
    /**
     * 提示消息
     */
    protected $message = [
        'title.require' => '商品名称必须填写',
        'category_ids.require' => '所属分类必须选择',
        'image.require' => '商品主图必须上传',
        'images.require' => '至少上传一张轮播图',
        'price.require' => '价格必须填写',
        'original_price.require' => '原价必须填写',
        'dispatch_type.require' => '发货方式必须选择',
        'dispatch_ids.require' => '配送模板必须选择',
        'service_ids.require' => '服务标签必须选择',
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['title', 'dispatch_type', 'dispatch_ids', 'image', 'images', 'category_ids','price', 'original_price'],
        'edit' => ['title', 'dispatch_type', 'dispatch_ids', 'image', 'images', 'category_ids','price', 'original_price'],
    ];

}
