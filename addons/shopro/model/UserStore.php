<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 用户门店
 */
class UserStore extends Model
{
    protected $name = 'shopro_user_store';
    // 关闭写入更新时间
    protected $autoWriteTimestamp = false;

    // 追加属性
    protected $append = [
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }
}
