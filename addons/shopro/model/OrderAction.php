<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use think\Db;

/**
 * 订单操作日志
 */
class OrderAction extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_order_action';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    public static function operAdd($order = null, $item = null, $oper = null, $type = 'user', $remark = '')
    {
        $oper_id = empty($oper) ? 0 : (is_array($oper) ? $oper['id'] : $oper->id);
        $self = new self();
        $self->order_id = $order['id'];
        $self->order_item_id = is_null($item) ? 0 : $item['id'];
        $self->oper_type = $type;
        $self->oper_id = $oper_id;
        $self->order_status = is_null($order) ? 0 : $order['status'];
        $self->dispatch_status = is_null($item) ? 0 : $item['dispatch_status'];
        $self->comment_status = is_null($item) ? 0 : $item['comment_status'];
        $self->aftersale_status = is_null($item) ? 0 : $item['aftersale_status'];
        $self->refund_status = is_null($item) ? 0 : $item['refund_status'];
        $self->remark = $remark;
        $self->save();

        return $self;
    }
}
