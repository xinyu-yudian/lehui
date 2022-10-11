<?php

namespace app\admin\model\shopro\wechat;

use think\Model;


class Fans extends Model
{
    // 表名
    protected $name = 'shopro_wechat_fans';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'subscribetime_text',
        'is_subscribe_text'
    ];
    

    
    public function getIsSubscribeList()
    {
        return ['0' => __('Is_subscribe 0'), '1' => __('Is_subscribe 1')];
    }


    public function getSubscribetimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['subscribetime']) ? $data['subscribetime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getIsSubscribeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_subscribe']) ? $data['is_subscribe'] : '');
        $list = $this->getIsSubscribeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setSubscribetimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
