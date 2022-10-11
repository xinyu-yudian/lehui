<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 活动-拼团
 */
class ActivityGrouponLog extends Model
{
    // 表名,不含前缀
    protected $name = 'shopro_activity_groupon_log';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['createtime', 'updatetime'];

    // 追加属性
    protected $append = [
    ];


    public function getUserAvatarAttr($value, $data)
    {
        if (!empty($value)) return cdnurl($value, true);
    }


    public function groupon() 
    {
        return $this->belongsTo(\addons\shopro\model\ActivityGroupon::class, 'groupon_id', 'id');
    }


    public function order()
    {
        return $this->belongsTo(\addons\shopro\model\Order::class, 'order_id', 'id');
    }
    
    public function goods()
    {
        return $this->belongsTo(\addons\shopro\model\Goods::class, 'goods_id', 'id');
    }
}
