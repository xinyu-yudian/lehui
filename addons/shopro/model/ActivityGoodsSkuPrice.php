<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;

/**
 * 活动模型
 */
class ActivityGoodsSkuPrice extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_activity_goods_sku_price';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime'];
    //列表动态隐藏字段
//    protected static $listHidden = ['content', 'params', 'images', 'service_ids'];

    // 追加属性
    protected $append = [
    ];
    
}
