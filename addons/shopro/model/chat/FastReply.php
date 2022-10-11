<?php

namespace addons\shopro\model\chat;

use app\admin\model\Admin;
use think\Model;

/**
 * 客服快捷回复
 */
class FastReply extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_chat_fast_reply';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['createtime', 'updatetime'];

    // 追加属性
    protected $append = [
    ];

}
