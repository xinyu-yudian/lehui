<?php

namespace app\admin\model\shopro\order;

use addons\shopro\library\traits\model\order\OrderOper;
use addons\shopro\library\traits\model\order\OrderScope;
use addons\shopro\library\traits\model\order\OrderStatus;
use app\admin\model\shopro\activity\Activity;
use think\Model;
use traits\model\SoftDelete;
use think\Log;

class Order extends Model
{

    use OrderOper, OrderScope, OrderStatus, SoftDelete;

    

    // 表名
    protected $name = 'shopro_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'status_text',
        'pay_type_text',
        'paytime_text',
        'platform_text',
        'ext_arr',
        'status_code',
        'status_name',
        'status_desc',
        'btns',
    ];

    // 订单状态
    const STATUS_INVALID = -2;      // 已失效|交易关闭
    const STATUS_CANCEL = -1;       // 已取消
    const STATUS_NOPAY = 0;         // 未付款
    const STATUS_PAYED = 1;         // 买家已付款
    const STATUS_FINISH = 2;        // 已完成
    
    public function getTypeList()
    {
        return ['goods' => __('Type goods'), 'score' => __('Type score')];
    }

    public function getStatusList()
    {
        return ['-2' => __('Status -2'), '-1' => __('Status -1'), '0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getPayTypeList()
    {
        return ['wechat' => __('Pay_type wechat'), 'alipay' => __('Pay_type alipay'), 'wallet' => __('Pay_type wallet'), 'score' => __('Pay_type score')];
    }

    public function getPlatformList()
    {
        return ['H5' => __('Platform h5'), 'wxOfficialAccount' => __('Platform wxofficialaccount'), 'wxMiniProgram' => __('Platform wxminiprogram'), 'App' => __('Platform app')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        return $this->getStatus($data, 'status_name');
    }


    public function getPayTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_type']) ? $data['pay_type'] : '');
        $list = $this->getPayTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getPlatformTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['platform']) ? $data['platform'] : '');
        $list = $this->getPlatformList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function getExtArrAttr($value, $data)
    {
        $ext = (isset($data['ext']) && $data['ext']) ? json_decode($data['ext'], true) : [];

        if ($ext && isset($ext['activity_discount_infos']) && $ext['activity_discount_infos']) {

            foreach ($ext['activity_discount_infos'] as $key => $info) {
                $ext['activity_discount_infos'][$key]['activity_type_text'] = Activity::getTypeList()[$info['activity_type']];
                $ext['activity_discount_infos'][$key]['format_text'] = \addons\shopro\model\Activity::formatDiscountTags($info['activity_type'], array_merge([
                    'type' => $info['rule_type'],
                ], $info['discount_rule']));
            }
        }
        return $ext;
    }


    protected function getStatus($data, $type)
    {
        $btns = [];
        $status_name = '';
        $status_desc = '';

        switch ($this->status_code) {
            case 'cancel':
                $status_name = '已取消';
                $status_desc = '买家已取消';
                break;
            case 'invalid':
                $status_name = '交易关闭';
                $status_desc = '买家未在规定时间内付款，订单将按照预定时间逾期自动关闭';
                break;
            case 'nopay':
                $status_name = '待付款';
                $status_desc = '买家已下单，等待付款';
                break;

                // 已支付的
            case 'commented':
                $status_name = '已评价';
                $status_desc = '买家已评价';
                $btns[] = 'refund';        // 全部退款  直接不申请退款
                break;
            case 'nocomment':
                $status_name = '未评价';
                $status_desc = '等待买家评价';
                $btns[] = 'refund';        // 全部退款  直接不申请退款
                break;
            case 'noget':
                $status_name = '商家已发货';
                $status_desc = '买家若在设置天数内未确认收货，系统将自动确认收货完成订单';
                $btns[] = 'refund';        // 全部退款  直接不申请退款
                break;
            case 'nosend':
                $is_send_btn = false;
                $is_send_store_btn = false;
                foreach ($this->item as $key => $item) {
                    // 获取 item status
                    $dispatchType[] = $item['dispatch_type'];

                    // 如果有一个快递的未发货，显示发货按钮
                    if ($item['dispatch_type'] == 'express' && $item['dispatch_status'] == OrderItem::DISPATCH_STATUS_NOSEND) {
                        $is_send_btn = true;
                    }

                    // 如果有一个到店，或者商家配送未发货，显示备货按钮
                    if (in_array($item['dispatch_type'], ['store', 'selfetch']) && $item['dispatch_status'] == OrderItem::DISPATCH_STATUS_NOSEND) {
                        $is_send_store_btn = true;
                    }
                }
                $dispatchType = array_unique(array_filter($dispatchType));
                if (in_array('express', $dispatchType)) {
                    $status_name = '买家已付款';
                    $status_desc = '买家已付款，请尽快发货';
                } else {
                    $status_name = '买家已付款';
                    $status_desc = '买家已付款，请通知相关门店发货';
                }

                if ($is_send_btn) {
                    $btns[] = 'send';              // 快递发货
                }

                if ($is_send_store_btn) {
                    $btns[] = 'send_store';       // 总后台也可直接帮门店发货
                }

                $btns[] = 'refund';        // 全部退款  直接不申请退款
                break;
            case 'refund_finish':
                $status_name = '退款完成';
                $status_desc = '订单退款完成';
                break;
            case 'refund_ing':
                $status_name = '退款处理中';
                $status_desc = '请尽快处理退款';
                $btns[] = 'refund';        // 全部退款  直接不申请退款
                break;
            case 'groupon_ing':
                $status_name = '等待成团';
                $status_desc = '请等待拼团完成之后再发货';
                $btns[] = 'refund';        // 全部退款  直接不申请退款
                break;
            case 'groupon_invalid':
                $status_name = '拼团失败';
                $status_desc = '未在规定时间完成拼团，团已解散';

                break;
                // 已支付的结束

            case 'finish':
                $status_name = '交易完成';
                $status_desc = '交易已完成';
                break;
        }

        if ($data['activity_type']) {
            // 有活动
            if (strpos($data['activity_type'], 'groupon') !== false) {
                // 是拼团订单
                $ext_arr = json_decode($data['ext'], true);
                if (isset($ext_arr['groupon_id']) && $ext_arr['groupon_id']) {
                    $btns[] = 'groupon';    // 拼团详情
                } else {
                    if ($data['status'] > 0) {
                        $btns[] = 'groupon_alone';    // 拼团单独购买，支付了，也没有 groupon_id 的情况
                    } else {
                        $btns[] = 'groupon';    // 拼团详情 未支付显示拼团
                    }
                }
            } else if (strpos($data['activity_type'], 'seckill') !== false) {
                // 秒杀订单
                $btns[] = 'seckill';    // 秒杀
            }
        }

        return $type == 'status_name' ? $status_name : ($type == 'btns' ? $btns : $status_desc);
    }



    public function user () 
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id');
    }

    public function item()
    {
        return $this->hasMany(\app\admin\model\shopro\order\OrderItem::class, 'order_id');
    }

    public function express()
    {
        return $this->hasMany(\app\admin\model\shopro\order\OrderExpress::class, 'order_id');
    }


    public function aftersale()
    {
        return $this->hasMany(\app\admin\model\shopro\order\Aftersale::class, 'order_id')->order('id', 'desc');
    }
}
