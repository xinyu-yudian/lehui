<?php

namespace app\admin\model\shopro;

use think\Model;
use traits\model\SoftDelete;

class Notification extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_notification';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'readtime_text'
    ];
    

    



    public function getReadtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['readtime']) ? $data['readtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setReadtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
