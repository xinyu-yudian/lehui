<?php

namespace app\admin\model\shopro\customerservice;

use think\Model;

/**
 * 客服表
 */
class CustomerService extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_customer_service';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
    ];


}
