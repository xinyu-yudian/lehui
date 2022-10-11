<?php

namespace app\admin\model\shopro\order;

use think\Model;


class OrderInvoice extends Model
{

    // 表名
    protected $name = 'shopro_order_invoice';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'status_text',
        'confirmtime_text'
    ];
    

    
    public function getTypeList()
    {
        return ['person' => __('Person'), 'company' => __('Company')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getConfirmtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['confirmtime']) ? $data['confirmtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setConfirmtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('\app\admin\model\shopro\user\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function order()
    {
        return $this->belongsTo('\app\admin\model\shopro\order\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
