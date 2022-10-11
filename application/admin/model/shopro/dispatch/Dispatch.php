<?php

namespace app\admin\model\shopro\dispatch;

use think\Model;
use traits\model\SoftDelete;

class Dispatch extends Model
{
    use SoftDelete;
    // 表名
    protected $name = 'shopro_dispatch';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
    ];
    
    public function getTypeList()
    {
        return ['express' => __('Type express'), 'selfetch' => __('Type selfetch'), 'store' => __('Type store'), 'autosend' => __('Type autosend')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

}
