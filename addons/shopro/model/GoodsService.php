<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 商品服务标签模型
 */
class GoodsService extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_goods_service';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime'];


    // 追加属性
    protected $append = [

    ];



    public function getImageAttr($value, $data)
    {
        if (!empty($value)) return cdnurl($value, true);

    }



}
