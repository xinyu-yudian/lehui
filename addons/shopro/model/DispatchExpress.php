<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Model;
use traits\model\SoftDelete;
/**
 * 快递模型
 */
class DispatchExpress extends Model
{
    use SoftDelete;

    protected $name = 'shopro_dispatch_express';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $hidden = ['createtime', 'updatetime', 'deletetime'];

    // 追加属性
    protected $append = [
        
    ];


    
}
