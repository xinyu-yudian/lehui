<?php

namespace addons\shopro\model\chat;

use think\Model;

/**
 * 客服连接表
 */
class Connection extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_chat_connection';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['createtime', 'updatetime'];

    // 追加属性
    protected $append = [
    ];


    public function chatUser() {
        return $this->belongsTo(User::class, 'session_id', 'session_id')->order('lasttime', 'desc');
    }
}
