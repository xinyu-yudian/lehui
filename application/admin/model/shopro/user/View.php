<?php

namespace app\admin\model\shopro\user;

use traits\model\SoftDelete;
use think\Model;

class View extends Model
{

    use SoftDelete;

    // 表名
    protected $name = 'shopro_user_view';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    public function goods()
    {
        return $this->belongsTo('\app\admin\model\shopro\goods\Goods', 'goods_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    

}
