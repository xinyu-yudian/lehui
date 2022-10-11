<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use think\Db;

/**
 * 订单包裹
 */
class OrderExpress extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_order_express';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['updatetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    protected $append = [
    ];


    public static function getList($params) {
        $user = User::info();
        $order_id = $params['order_id'] ?? 0;

        $list = self::with(['log', 'item' => function ($query) use ($order_id) {
            return $query->where('order_id', $order_id);
        }])->where('user_id', $user->id)->where('order_id', $order_id)->select();

        return $list;
    }


    public static function detail($params) {
        $user = User::info();
        $id = $params['id'] ?? 0;
        $order_id = $params['order_id'] ?? 0;

        $detail = self::with(['log', 'item' => function ($query) use ($order_id) {
            return $query->where('order_id', $order_id);
        }])->where('user_id', $user->id)->where('order_id', $order_id)->where('id', $id)->find();

        return $detail;
    }


    /**
     * 记得用的时候一定要额外加上 order_id 等于当前订单号
     */
    public function item()
    {
        return $this->hasMany(\addons\shopro\model\OrderItem::class, 'express_no', 'express_no');
    }


    public function log()
    {
        return $this->hasMany(\addons\shopro\model\OrderExpressLog::class, 'order_express_id')->order('id', 'desc');
    }


    public function order()
    {
        return $this->belongsTo(\addons\shopro\model\Order::class, 'order_id');
    }
}
