<?php

namespace addons\shopro\model\chat;

use think\Model;
use addons\shopro\library\chat\Online;
/**
 * 客服系统用户表
 */
class User extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_chat_user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
    ];


    public function getAvatarAttr($value, $data)
    {
        if (!empty($value)) return Online::cdnurl($value);
    }

    /** 当前thinkphp 多态关联有 bug */
    // public function user()
    // {
    //     return $this->morphOne(\addons\shopro\model\chat\Log::class, ['sender_identify', 'sender_id'], 'user');
    // }
}
