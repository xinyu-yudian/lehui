<?php

namespace app\admin\model\shopro;

use think\Model;
use traits\model\SoftDelete;

class Feedback extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_feedback';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text', 'type_text'
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


    public function getTypeTextAttr($value, $data)
    {
        return isset(\addons\shopro\model\Feedback::$typeAll[$data['type']]) ? \addons\shopro\model\Feedback::$typeAll[$data['type']]['name'] : '';
    }

    public function user()
    {
        return $this->belongsTo('\app\admin\model\shopro\user\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
