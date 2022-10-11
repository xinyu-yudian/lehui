<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;

/**
 * 优惠券模型
 */
class UserCoupons extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_user_coupons';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;



    // 追加属性
    protected $append = [

    ];




    public function userCoupons($value, $data)
    {

    }



}
