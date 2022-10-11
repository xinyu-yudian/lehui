<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;

/**
 * 消息模型
 */
class Notification extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_notification';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        
    ];
}
