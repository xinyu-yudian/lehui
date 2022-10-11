<?php

namespace app\admin\model\shopro\order;

use think\Model;

class OrderAction extends Model
{
    // 表名
    protected $name = 'shopro_order_action';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
    ];

    
    public function oper() {
        // 用户和system 都不需要关联，这里直接只关联 admin，列表循环处理
        return $this->belongsTo(\app\admin\model\Admin::class, 'oper_id', 'id');
    }


}
