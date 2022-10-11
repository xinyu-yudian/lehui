<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 常见问题
 */
class Faq extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_faq';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['deletetime'];

    // 追加属性
    protected $append = [
    ];


}
