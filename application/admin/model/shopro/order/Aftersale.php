<?php

namespace app\admin\model\shopro\order;

use think\Model;
use traits\model\SoftDelete;
use addons\shopro\library\traits\model\order\OrderAftersaleScope;
use app\admin\model\shopro\activity\Activity;

class Aftersale extends Model
{

    use SoftDelete, OrderAftersaleScope;

    

    // 表名
    protected $name = 'shopro_order_aftersale';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'dispatch_status_text',
        'aftersale_status_text',
        'aftersale_status_desc',
        'refund_status_text',
        'activity_type_arr',
        'activity_type_text_arr',
    ];
    

    // 发货状态
    const DISPATCH_STATUS_NOSEND = 0;       // 未发货
    const DISPATCH_STATUS_SENDED = 1;       // 已发货
    const DISPATCH_STATUS_GETED = 2;        // 已收货

    // 售后状态
    const AFTERSALE_STATUS_CANCEL = -2;       // 售后取消
    const AFTERSALE_STATUS_REFUSE = -1;       // 拒绝
    const AFTERSALE_STATUS_NOOPER = 0;       // 未处理
    const AFTERSALE_STATUS_AFTERING = 1;       // 处理中
    const AFTERSALE_STATUS_OK = 2;        // 售后完成


    // 退款状态
    const REFUND_STATUS_REFUSE = -1;       // 拒绝退款(不用了)
    const REFUND_STATUS_NOREFUND = 0;       // 未退款
    const REFUND_STATUS_FINISH = 1;       // 同意


    public function getTypeList()
    {
        return ['refund' => __('Type refund'), 'return' => __('Type return'), 'other' => __('Type other')];
    }

    public function getDispatchStatusList()
    {
        return ['0' => __('Dispatch_status 0'), '1' => __('Dispatch_status 1'), '2' => __('Dispatch_status 2')];
    }

    public function getAftersaleStatusList()
    {
        return ['-2' => __('Aftersale_status -2'), '-1' => __('Aftersale_status -1'), '0' => __('Aftersale_status 0'), '1' => __('Aftersale_status 1'), '2' => __('Aftersale_status 2')];
    }

    public function getAftersaleStatusDescList()
    {
        return ['-2' => __('Aftersale_status_desc -2'), '-1' => __('Aftersale_status_desc -1'), '0' => __('Aftersale_status_desc 0'), '1' => __('Aftersale_status_desc 1'), '2' => __('Aftersale_status_desc 2')];
    }

    public function getRefundStatusList()
    {
        return ['-1' => __('Refund_status -1'), '0' => __('Refund_status 0'), '1' => __('Refund_status 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getActivityTypeArrAttr($value, $data) {
        $activity_types = $value ? $value : (isset($data['activity_type']) ? $data['activity_type'] : '');
        $activityTypes = array_values(array_filter(explode(',', $activity_types)));

        return $activityTypes;
    }

    public function getActivityTypeTextArrAttr($value, $data)
    {
        $activityTypes = $this->activity_type_arr;
        $list = \app\admin\model\shopro\activity\Activity::getTypeList();

        $activityTypeTextArr = [];
        foreach ($activityTypes as $key => $activity_type) {
            if (isset($list[$activity_type])) {

                $activityTypeTextArr[$activity_type] = $list[$activity_type];
            }
        }

        return $activityTypeTextArr;
    }




    public function getDispatchStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['dispatch_status']) ? $data['dispatch_status'] : '');
        $list = $this->getDispatchStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getAftersaleStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['aftersale_status']) ? $data['aftersale_status'] : '');
        $list = $this->getAftersaleStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getAftersaleStatusDescAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['aftersale_status']) ? $data['aftersale_status'] : '');
        $list = $this->getAftersaleStatusDescList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getRefundStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['refund_status']) ? $data['refund_status'] : '');
        $list = $this->getRefundStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(\app\admin\model\shopro\order\Order::class, 'order_id', 'id');
    }


    public function logs()
    {
        return $this->hasMany(AftersaleLog::class, 'order_aftersale_id', 'id')->order('id', 'desc');
    }

}
