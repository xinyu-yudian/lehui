<?php

namespace app\admin\model\shopro\order;

use addons\shopro\library\traits\model\order\TradeOrderStatus;
use think\Model;
use traits\model\SoftDelete;

class TradeOrder extends Model
{

    use TradeOrderStatus, SoftDelete;

    

    // 表名
    protected $name = 'shopro_trade_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_code',
        'status_name',
        'status_desc',
        'pay_type_text',
        'pay_time_text',
        'platform_text',
        'ext_arr'
    ];

    // 订单状态
    const STATUS_INVALID = -2;      // 已失效|交易关闭
    const STATUS_CANCEL = -1;       // 已取消
    const STATUS_NOPAY = 0;         // 未付款
    const STATUS_PAYED = 1;         // 买家已付款
    const STATUS_FINISH = 2;        // 已完成


    

    public function user () 
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id');
    }

}
