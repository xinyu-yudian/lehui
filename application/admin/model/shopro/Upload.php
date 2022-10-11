<?php

namespace app\admin\model\shopro;

use think\Model;
use traits\model\SoftDelete;

class Upload extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_link';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [

    ];
    

    







}
