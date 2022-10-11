<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use think\Db;

/**
 * 订单售后操作日志
 */
class OrderAftersaleLog extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_order_aftersale_log';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['updatetime', 'deletetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    protected $append = [
    ];

    public static function operAdd($order = null, $aftersale = null, $oper = null, $type = 'user', $data = [])
    {
        $oper_id = empty($oper) ? 0 : (is_array($oper) ? $oper['id'] : $oper->id);
        $images = $data['images'] ?? [];
        $images = is_array($images) ? implode(',', $images) : $images;
        
        $self = new self();
        $self->order_id = $order['id'] ?? ($aftersale['order_id'] ?? 0);
        $self->order_aftersale_id = is_null($aftersale) ? 0 : $aftersale['id'];
        $self->oper_type = $type;
        $self->oper_id = $oper_id;
        $self->dispatch_status = is_null($aftersale) ? 0 : $aftersale['dispatch_status'];
        $self->aftersale_status = is_null($aftersale) ? 0 : $aftersale['aftersale_status'];
        $self->refund_status = is_null($aftersale) ? 0 : $aftersale['refund_status'];
        $self->reason = $data['reason'] ?? '';
        $self->content = $data['content'] ?? '';
        $self->images = $images;
        $self->save();

        // 售后单变动行为
        $data = ['aftersale' => $aftersale, 'order' => $order, 'aftersaleLog' => $self];
        \think\Hook::listen('aftersale_change', $data);

        return $self;
    }


    public function getImagesAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($value)) {
            $imagesArray = explode(',', $value);
            foreach ($imagesArray as &$v) {
                $v = cdnurl($v, true);
            }
            return $imagesArray;
        }
        return $imagesArray;
    }
}
