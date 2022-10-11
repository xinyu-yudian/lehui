<?php

namespace addons\shopro\model;

use think\Model;


class UserFake extends Model
{

    // 表名
    protected $name = 'shopro_user_fake';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 追加属性
    protected $append = [];
}
