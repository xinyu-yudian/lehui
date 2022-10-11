<?php

namespace app\admin\model\shopro;

use think\Model;

/**
 * 分类模型
 */
class Category extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_category';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
    ];

    public function children () 
    {
        return $this->hasMany(\app\admin\model\shopro\Category::class, 'pid', 'id')->order('weigh desc, id asc');
    }
}
