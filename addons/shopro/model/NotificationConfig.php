<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 消息模型
 */
class NotificationConfig extends Model
{
    // 表名,不含前缀
    protected $name = 'shopro_notification_config';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'content_arr'
    ];


    public function getContentArrAttr($value, $data)
    {
        return (isset($data['content']) && $data['content']) ? json_decode($data['content'], true) : [];
    }
}
