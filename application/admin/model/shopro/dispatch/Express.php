<?php

namespace app\admin\model\shopro\dispatch;

use think\Model;
use traits\model\SoftDelete;

class Express extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_dispatch_express';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'area_text'
    ];
    

    public function getTypeList()
    {
        return ['number' => __('Type number'), 'weight' => __('Type weight')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getAreaTextAttr($value, $data)
    {
        $province_ids = explode(',', $data['province_ids']);
        $city_ids = explode(',', $data['city_ids']);
        $area_ids = explode(',', $data['area_ids']);
        $ids = array_merge($province_ids, $city_ids, $area_ids);
        $provinceText = \app\admin\model\shopro\Area::where('id', 'in', $ids)->field('name')->select();
        $provinceText = array_column($provinceText, 'name');
        return implode(',', $provinceText);
    }




}
