<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 商品服务标签模型
 */
class GoodsSku extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_goods_sku';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 追加属性
    protected $append = [

    ];




}
