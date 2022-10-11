<?php

namespace app\admin\model\shopro\store;

use think\Model;


class Apply extends Model
{

    

    

    // 表名
    protected $name = 'shopro_store_apply';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'openweeks_text',
        'status_text'
    ];
    

    
    public function getOpenweeksList()
    {
        return ['1' => __('Openweeks 1'), '2' => __('Openweeks 2'), '3' => __('Openweeks 3'), '4' => __('Openweeks 4'), '5' => __('Openweeks 5'), '6' => __('Openweeks 6'), '7' => __('Openweeks 7')];
    }

    public function getStatusList()
    {
        return ['-1' => __('Status -1'), '0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getOpenweeksTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['openweeks']) ? $data['openweeks'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getOpenweeksList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setOpenweeksAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


    public function user()
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }
}
