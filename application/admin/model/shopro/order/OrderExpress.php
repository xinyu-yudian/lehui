<?php

namespace app\admin\model\shopro\order;

use think\Model;

class OrderExpress extends Model
{
    // 表名
    protected $name = 'shopro_order_express';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
    ];


    /**
     * 记得用的时候一定要额外加上 order_id 等于当前订单号
     */
    public function item()
    {
        return $this->hasMany(\app\admin\model\shopro\order\OrderItem::class, 'express_no', 'express_no');
    }

    public function order()
    {
        return $this->belongsTo(\addons\shopro\model\Order::class, 'order_id');
    }


    public function log()
    {
        return $this->hasMany(\app\admin\model\shopro\order\OrderExpressLog::class, 'order_express_id')->order('id', 'desc');
    }
}
