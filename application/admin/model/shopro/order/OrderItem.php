<?php

namespace app\admin\model\shopro\order;

use think\Model;
use traits\model\SoftDelete;
use addons\shopro\library\traits\model\order\OrderItemStatus;

class OrderItem extends Model
{

    use OrderItemStatus, SoftDelete;

    

    // 表名
    protected $name = 'shopro_order_item';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'dispatch_type_text',
        'dispatch_status_text',
        'aftersale_status_text',
        'comment_status_text',
        'activity_type_arr',
        'activity_type_text_arr',
        'activity_type_text',
        'refund_status_text',
        'status_code',
        'status_name',
        'status_desc',
        'btns',
        'ext_arr'
    ];

    // 发货状态
    const DISPATCH_STATUS_NOSEND = 0;       // 未发货
    const DISPATCH_STATUS_SENDED = 1;       // 已发货
    const DISPATCH_STATUS_GETED = 2;        // 已收货


    // 售后状态
    const AFTERSALE_STATUS_REFUSE = -1;       // 驳回
    const AFTERSALE_STATUS_NOAFTER = 0;       // 未申请
    const AFTERSALE_STATUS_AFTERING = 1;       // 申请退款
    const AFTERSALE_STATUS_OK = 2;        // 售后完成


    // 退款状态
    const REFUND_STATUS_REFUSE = -1;       // 拒绝退款 // 驳回aftersale_status（状态不会出现）
    const REFUND_STATUS_NOREFUND = 0;       // 退款状态 未申请
    const REFUND_STATUS_ING = 1;       // 申请中         // 不需要申请（状态不会出现）
    const REFUND_STATUS_OK = 2;       // 已同意
    const REFUND_STATUS_FINISH = 3;       // 退款完成

    // 评价状态
    const COMMENT_STATUS_NO = 0;       // 待评价
    const COMMENT_STATUS_OK = 1;       // 已评价

    public function getExtArrAttr($value, $data)
    {
        return (isset($data['ext']) && $data['ext']) ? json_decode($data['ext'], true) : [];
    }
    
    public function getDispatchStatusList()
    {
        return ['0' => __('Dispatch_status 0'), '1' => __('Dispatch_status 1'), '2' => __('Dispatch_status 2')];
    }

    public function getAftersaleStatusList()
    {
        return ['-1' => __('Aftersale_status -1'), '0' => __('Aftersale_status 0'), '1' => __('Aftersale_status 1'), '2' => __('Aftersale_status 2')];
    }

    public function getCommentStatusList()
    {
        return ['0' => __('Comment_status 0'), '1' => __('Comment_status 1')];
    }

    public function getRefundStatusList()
    {
        return ['-1' => __('Refund_status -1'), '0' => __('Refund_status 0'), '1' => __('Refund_status 1'), '2' => __('Refund_status 2'), '3' => __('Refund_status 3')];
    }


    public function getDispatchTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['dispatch_type']) ? $data['dispatch_type'] : '');
        $list = (new \app\admin\model\shopro\dispatch\Dispatch)->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
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


    public function getActivityTypeTextAttr($value, $data) {
        $activityTypeTextArr = $this->activityTypeTextArr;
        return join(',', $activityTypeTextArr);
    }


    public function getCommentStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['comment_status']) ? $data['comment_status'] : '');
        $list = $this->getCommentStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getRefundStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['refund_status']) ? $data['refund_status'] : '');
        $list = $this->getRefundStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatus($data, $type)
    {
        $btns = [];
        $status_name = '';
        $status_desc = '';

        $status_code = $this->status_code;

        $item_code = 'null';        // 有售后的时候，第二状态
        if (strpos($status_code, '|') !== false) {
            $codes = explode('|', $status_code);
            $status_code = $codes[0] ?? 'null';
            $item_code = $codes[1] ?? 'null';
        }

        switch ($status_code) {
            case 'null':
            case 'cancel':
            case 'invalid':
            case 'nopay':
                // 未支付的返回空
                break;
            case 'nosend':
                $status_name = '待发货';
                $status_desc = '等待卖家发货';

                // 细分订单发货
                switch ($data['dispatch_type']) {
                    case 'store':
                        $status_name = '待配送';
                        $status_desc = '等待卖家上门配送';
                        $btns[] = 'send_store';       // 总后台也可直接帮门店发货
                        break;
                    case 'selfetch':
                        $status_name = '待备货';
                        $status_desc = '等待卖家备货';
                        $btns[] = 'send_store';       // 总后台也可直接帮门店发货
                        break;
                    case 'express':
                        $btns[] = 'send';          // 发货
                        break;
                }

                $btns[] = 'refund';          // 退款
                break;
            case 'noget':
                switch ($data['dispatch_type']) {
                    case 'express':
                        $status_name = '待收货';
                        $status_desc = '等待买家收货';
                        $btns[] = 'get';            // 确认收货
                        break;
                    case 'selfetch':
                        $status_name = '待自提/到店';
                        $status_desc = '等待买家提货/到店';
                        break;
                    case 'store':
                        $status_name = '待取货';
                        $status_desc = '卖家上门配送中';
                        break;
                    case 'autosend':        // 正常不需要确认收货
                        $status_name = '待收货';
                        $status_desc = '等待买家收货';
                        $btns[] = 'get';            // 确认收货
                        break;
                }
                $btns[] = 'refund';          // 售后
                break;
            case 'nocomment':
                $status_name = '待评价';
                $status_desc = '等待买家评价';
                $btns[] = 'refund';          // 退款
                break;
            case 'commented':
                $status_name = '已评价';
                $status_desc = '订单已评价';
                $btns[] = 'refund';          // 退款
                break;
            case 'refund_finish':
                $status_name = '退款完成';
                $status_desc = '订单退款完成';
                break;
            case 'refund_ing':      // 不需要申请退款（状态不会出现）
                $status_name = '退款处理中';
                $status_desc = '退款处理中';
                $btns[] = 'refund';           // 退款
                break;
            case 'refund_refuse':   // 驳回aftersale_status（状态不会出现）
                $status_name = '拒绝退款';
                $status_desc = '卖家拒绝退款';
                break;
            case 'after_ing':
                $status_name = '售后中';
                $status_desc = '售后处理中';
                break;
            case 'after_refuse':
            case 'after_finish':
                switch ($status_code) {
                    case 'after_refuse':
                        $status_name = '售后拒绝';
                        $status_desc = '售后申请拒绝';
                        break;
                    case 'after_finish':
                        $status_name = '售后完成';
                        $status_desc = '售后完成';
                        break;
                }

                // 售后拒绝，或者完成的时候，还可以继续操作订单
                switch ($item_code) {
                    case 'nosend':
                        if ($data['dispatch_type'] == 'express') {
                            $btns[] = 'send';           // 发货
                        } else if (in_array($data['dispatch_type'], ['store', 'selfetch'])) {
                            $btns[] = 'send_store';     // 备货
                        }
                        break;
                }

                break;
        }

        // 只要有售后单号，就显示售后详情按钮
        $ext_arr = json_decode($data['ext'], true);
        if (isset($ext_arr['aftersale_id']) && !empty($ext_arr['aftersale_id'])) {
            $btns[] = 'aftersale_info';     // 查看详情
        }

        return $type == 'status_name' ? $status_name : ($type == 'btns' ? $btns : $status_desc);
    }


    // 商家配送， 到店自提的门店
    public function store()
    {
        return $this->belongsTo(\app\admin\model\shopro\store\Store::class, 'store_id');
    }


    public function reward()
    {
        return $this->hasMany(\app\admin\model\shopro\commission\Reward::class, 'order_item_id', 'id');
    }


    public function commissionOrder()
    {
        return $this->hasOne(\app\admin\model\shopro\commission\Order::class, 'order_item_id');
    }
}
