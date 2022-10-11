<?php

namespace app\admin\model\shopro\user;

use think\Model;

class Oauth extends Model
{

    // 表名
    protected $name = 'shopro_user_oauth';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        // 'prevtime_text',
        // 'logintime_text',
        // 'jointime_text'
    ];


}
