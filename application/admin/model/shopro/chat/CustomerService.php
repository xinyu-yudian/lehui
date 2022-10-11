<?php

namespace app\admin\model\shopro\chat;

use think\Model;


class CustomerService extends Model
{

    

    

    // 表名
    protected $name = 'shopro_chat';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'lasttime_text',
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['offline' => __('Status offline'), 'online' => __('Status online'), 'busy' => __('Status busy')];
    }


    public function getLasttimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lasttime']) ? $data['lasttime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setLasttimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function admin () {
        return $this->belongsTo(\app\admin\model\Admin::class, 'admin_id')->field('id,username,nickname,avatar');
    }

}
