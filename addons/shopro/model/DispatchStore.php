<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Model;
use traits\model\SoftDelete;
/**
 * dispatch 商家配送模板
 */
class DispatchStore extends Model
{
    use SoftDelete;

    protected $name = 'shopro_dispatch_store';
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
