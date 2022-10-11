<?php

namespace app\admin\model\shopro\order;

use think\Model;

class OrderExpressLog extends Model
{
    // 表名
    protected $name = 'shopro_order_express_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'status_name'
    ];


    public function getStatusNameAttr($value, $data)
    {
        $status = substr($data['status'], 0, 1);

        $list = \addons\shopro\model\OrderExpressLog::getStatusList();
        return isset($list[$status]) ? $list[$status] : '';
    }
}
