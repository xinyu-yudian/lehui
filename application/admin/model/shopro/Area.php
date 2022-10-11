<?php

namespace app\admin\model\shopro;

use think\Model;


class Area extends Model
{

    // 表名
    protected $name = 'shopro_area';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'value',
        'label'
    ];
    

    public function getValueAttr($value, $data) {
        return $data['id'];
    }

    public function getLabelAttr($value, $data)
    {
        return $data['name'];
    }
    
    public function children () 
    {
        return $this->hasMany(\app\admin\model\shopro\Area::class, 'pid', 'id');
    }

}
