<?php

namespace app\admin\model\shopro;

use think\Model;


class Work extends Model
{

    

    

    // 表名
    protected $name = 'shopro_work';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'cate_text',
        'status_text'
    ];
    

    
    public function getCateList()
    {
        return ['goods' => __('Goods'), 'master' => __('Master')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }


    public function getCateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cate']) ? $data['cate'] : '');
        $list = $this->getCateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
