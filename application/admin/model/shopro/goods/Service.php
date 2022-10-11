<?php

namespace app\admin\model\shopro\goods;

use think\Model;
use traits\model\SoftDelete;

class Service extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_goods_service';
    
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
