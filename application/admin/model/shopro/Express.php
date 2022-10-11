<?php

namespace app\admin\model\shopro;

use think\Model;


class Express extends Model
{

    // 快递公司表
    protected $name = 'shopro_express';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
    ];
    



}
