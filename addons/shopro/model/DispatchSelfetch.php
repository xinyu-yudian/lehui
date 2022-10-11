<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Model;
use traits\model\SoftDelete;
/**
 * dispatch 到店自提模板
 */
class DispatchSelfetch extends Model
{
    use SoftDelete;

    protected $name = 'shopro_dispatch_selfetch';
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
