<?php

namespace app\admin\model\shopro\order;

use think\Model;

class Verify extends Model
{


    // 表名
    protected $name = 'shopro_verify';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'type_text',
        'status_code',
        'status_name'
    ];


    public function getTypeList()
    {
        return [
            'verify' => '核销券'
        ];
    }

    public function scopeCanUse($query)
    {
        return $query->where('usetime', null)->where(function ($query) {
            $query->where('expiretime', null)->whereOr('expiretime', '>', time());
        });
    }



    public function getTypeTextAttr($value, $data)
    {
        $value = isset($data['type']) ? $data['type'] : '';
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }



    public function getStatusNameAttr($value, $data)
    {
        $status_name = '';
        switch ($this->status_code) {
            case 'used':
                $status_name = '已使用';
                break;
            case 'nouse':
                $status_name = '未使用';
                break;
            case 'expire':
                $status_name = '已过期';
                break;
        }

        return $status_name;
    }


    public function getStatusCodeAttr($value, $data)
    {
        $status_code = '';

        if ($data['usetime']) {
            // 已使用
            $status_code = 'used';
        } else {
            // 未使用
            if (is_null($data['expiretime']) || $data['expiretime'] > time()) {
                // 不过期，或者过期时间大于当前时间
                $status_code = 'nouse';
            } else {
                // 已过期
                $status_code = 'expire';
            }
        }

        return $status_code;
    }


    public function orderItem()
    {
        return $this->belongsTo(\app\admin\model\shopro\order\OrderItem::class, 'order_item_id', 'id');
    }


    public function order()
    {
        return $this->belongsTo(\app\admin\model\shopro\order\Order::class, 'order_id', 'id');
    }
}
