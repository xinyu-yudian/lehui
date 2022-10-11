<?php

namespace app\admin\model\shopro\user;

use think\Model;

class Coupon extends Model
{

    // 表名
    protected $name = 'shopro_user_coupons';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    public function order()
    {
        return $this->belongsTo('\app\admin\model\shopro\order\Order', 'use_order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function coupons()
    {
        return $this->belongsTo('\app\admin\model\shopro\Coupons', 'coupons_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    

}
