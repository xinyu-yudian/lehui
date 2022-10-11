<?php

namespace app\admin\model\shopro\commission;

use think\Model;

class Config extends Model
{
    // 表名
    protected $name = 'shopro_commission_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 追加属性
    protected $append = [
    ];

}
