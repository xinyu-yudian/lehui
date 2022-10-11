<?php

namespace app\admin\model\shopro\activity;

use think\Model;


class GrouponLog extends Model
{

    // 表名
    protected $name = 'shopro_activity_groupon_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    

    
    public function getStatusList()
    {
        return ['invalid' => __('Status invalid'), 'ing' => __('Status ing'), 'finish' => __('Status finish'), 'finish-fictitious' => __('Status finish-fictitious')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function grouponLog()
    {
        return $this->hasMany(\addons\shopro\model\ActivityGroupon::class, 'groupon_id', 'id');
    }


    public function user()
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function goods()
    {
        return $this->belongsTo(\app\admin\model\shopro\goods\Goods::class, 'goods_id', 'id');
    }

}
