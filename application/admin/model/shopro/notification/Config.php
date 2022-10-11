<?php

namespace app\admin\model\shopro\notification;

use think\Model;


class Config extends Model
{

    

    

    // 表名
    protected $name = 'shopro_notification_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'content_arr'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getContentArrAttr($value, $data)
    {
        return (isset($data['content']) && $data['content']) ? json_decode($data['content'], true) : [];
    }

}
