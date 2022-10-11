<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 第三方授权模型
 */
class UserOauth extends Model
{
    protected $name = 'shopro_user_oauth';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
    ];



}
