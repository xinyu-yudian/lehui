<?php

namespace app\admin\model\shopro;

use think\Model;

class DecoratePage extends Model
{
    // 表名
    protected $name = 'shopro_decorate_page';

    // 定义时间戳字段名
    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;





}
