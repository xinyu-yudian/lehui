<?php

namespace addons\shopro\model\chat;

use app\admin\model\Admin;
use think\Model;
use addons\shopro\library\chat\Online;

/**
 * 客服表
 */
class CustomerService extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_chat';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['createtime', 'updatetime'];

    // 追加属性
    protected $append = [
    ];


    public function getAvatarAttr($value, $data)
    {
        if (!empty($value)) return Online::cdnurl($value);
    }


    public function admin () {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }


    /** 当前thinkphp 多态关联有 bug */
    // public function customerService()
    // {
    //     return $this->morphOne(\addons\shopro\model\chat\Log::class, ['sender_identify', 'sender_id'], 'customer_service');
    // }
}
