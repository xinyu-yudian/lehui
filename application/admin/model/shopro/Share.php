<?php

namespace app\admin\model\shopro;

use think\Model;

/**
 * 分享模型
 */
class Share extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_share';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;


    // 追加属性
    protected $append = [
        'type_text',
    ];

    public function getTypeList()
    {
        return ['index' => __('Type index'), 'goods' => __('Type goods'), 'groupon' => __('Type groupon')];
    }

    
    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function user()
    {
        return $this->belongsTo('\app\admin\model\shopro\user\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    

}
