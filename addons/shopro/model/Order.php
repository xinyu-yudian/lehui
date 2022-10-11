<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use addons\shopro\library\traits\model\order\OrderScope;
use think\Db;
use think\Queue;
use traits\model\SoftDelete;
use addons\shopro\library\traits\model\order\OrderOper;
use addons\shopro\library\traits\model\order\OrderStatus;

/**
 * 订单模型
 */
class Order extends Model
{
    use SoftDelete, OrderOper, OrderScope, OrderStatus;

    // 表名,不含前缀
    protected $name = 'shopro_order';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['updatetime', 'deletetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    // // 追加属性
    protected $append = [
        'status_code',
        'status_name',
        'status_desc',
        'btns',
        'ext_arr'
    ];

    // 订单状态
    const STATUS_INVALID = -2;
    const STATUS_CANCEL = -1;
    const STATUS_NOPAY = 0;
    const STATUS_PAYED = 1;
    const STATUS_FINISH = 2;


    /* -------------------------- 访问器 ------------------------ */

    public function getExtArrAttr($value, $data)
    {
        $ext = (isset($data['ext']) && $data['ext']) ? json_decode($data['ext'], true) : [];

        if ($ext && isset($ext['activity_discount_infos']) && $ext['activity_discount_infos']) {

            foreach ($ext['activity_discount_infos'] as $key => $info) {
                $ext['activity_discount_infos'][$key]['activity_type_text'] = Activity::getTypeList()[$info['activity_type']];
                $ext['activity_discount_infos'][$key]['format_text'] = Activity::formatDiscountTags($info['activity_type'], array_merge([
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
                $status_desc = '订单已取消';
                $btns[] = 'delete';     // 删除订单
                break;
            case 'invalid':
                $status_name = '交易关闭';
                $status_desc = '交易关闭';
                $btns[] = 'delete';     // 删除订单
                break;
            case 'nopay':
                $status_name = '待付款';
                $status_desc = '等待买家付款';
                $btns[] = 'cancel';     // 取消订单
                $btns[] = 'pay';        // 支付
                break;

            // 已支付的
            case 'commented':
                $status_name = '已评价';
                $status_desc = '订单已评价';

                $dispatchType = $this->getItemDispatchType($this->item);
                if (in_array('express', $dispatchType)) {
                    $btns[] = 'express';        // 查看物流
                }

                break;
            case 'nocomment':
                $status_name = '待评价';
                $status_desc = '等待买家评价';

                $dispatchType = $this->getItemDispatchType($this->item);
                if (in_array('express', $dispatchType)) {
                    $btns[] = 'express';        // 查看物流
                }
                
                break;
            case 'noget':
                $dispatchType = $this->getItemDispatchType($this->item);
                
                $status_name = '待收货';
                $status_desc = '等待买家收货';
                if (in_array('express', $dispatchType)) {
                    $btns[] = 'express';        // 查看物流
                } else {
                    if (count($dispatchType) == 1) {
                        // item 只有一种发货方式
                        $dispatch_type = $dispatchType[0] ?? '';

                        switch($dispatch_type) {
                            case 'selfetch':
                                $status_name = '待提货/到店';
                                $status_desc = '等待买家提货/到店';
                                break;
                            case 'store':
                                $status_name = '待取货';
                                $status_desc = '卖家上门配送中';
                                break;
                        }
                    }
                }
                break;
            case 'nosend':
                $dispatchType = $this->getItemDispatchType($this->item);

                $status_name = '待发货';
                $status_desc = '等待卖家发货';
                if (in_array('store', $dispatchType)) {
                    $status_name = '待配送';
                    $status_desc = '等待卖家上门配送';
                } else if (in_array('selfetch', $dispatchType)) {
                    $status_name = '待备货';
                    $status_desc = '等待卖家备货';
                }
                break;
            case 'refund_finish':
                $status_name = '退款完成';
                $status_desc = '订单退款完成';

                break;
            case 'refund_ing':
                $status_name = '退款处理中';
                $status_desc = '退款处理中';

                break;
            case 'groupon_ing':
                $status_name = '等待成团';
                $status_desc = '等待拼团成功';
                
                break;
            case 'groupon_invalid':
                $status_name = '拼团失败';
                $status_desc = '拼团失败';

                break;
            // 已支付的结束
            
            case 'finish':
                $status_name = '交易完成';
                $status_desc = '交易完成';
                $btns[] = 'delete';     // 删除订单
                break;
        }

        $ext_arr = json_decode($data['ext'], true);
        // 是拼团订单
        if (
            strpos($data['activity_type'], 'groupon') !== false &&
            isset($ext_arr['groupon_id']) && $ext_arr['groupon_id']
        ) {
            $btns[] = 'groupon';    // 拼团详情
        }

        return $type == 'status_name' ? $status_name : ($type == 'btns' ? $btns : $status_desc);
    }



    private function getItemDispatchType($item = []) {
        $dispatchType = [];
        foreach ($this->item as $key => $item) {
            // 获取 item status
            $dispatchType[] = $item['dispatch_type'];
        }
        $dispatchType = array_unique(array_filter($dispatchType));  // 过滤重复，过滤空值

        return $dispatchType;
    }

    /* -------------------------- 访问器 ------------------------ */



    /* -------------------------- 模型关联 ------------------------ */

    public function item()
    {
        return $this->hasMany(\addons\shopro\model\OrderItem::class, 'order_id', 'id');
    }


    /**
     * 只保留基本信息
     */
    public function itemSlim()
    {
        return $this->hasMany(\addons\shopro\model\OrderItem::class, 'order_id', 'id')
                    ->field('id,user_id,order_id,goods_id,goods_type,goods_sku_price_id,activity_id,activity_type,item_goods_sku_price_id,goods_sku_text,goods_title,goods_image,goods_original_price,discount_fee,goods_price,goods_num,dispatch_status,dispatch_type,dispatch_id,store_id,aftersale_status,comment_status,refund_status,ext');
    }

    // 拼团只有一个商品，可以使用这个
    public function firstItem()
    {
        return $this->hasOne(\addons\shopro\model\OrderItem::class, 'order_id', 'id');
    }
    /* -------------------------- 模型关联 ------------------------ */
}

