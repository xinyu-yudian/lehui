<?php

namespace addons\shopro\model\chat;

use app\admin\model\Admin;
use think\Model;

/**
 * 常见问题
 */
class Question extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_chat_question';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = [];

    // 追加属性
    protected $append = [
    ];


    public function scopeShow($query)
    {
        return $query->where('status', 'normal');
    }
}
