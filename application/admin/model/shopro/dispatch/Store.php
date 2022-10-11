<?php

namespace app\admin\model\shopro\dispatch;

use think\Model;
use traits\model\SoftDelete;

class Store extends Model
{

    use SoftDelete;

    // 表名
    protected $name = 'shopro_dispatch_store';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

     // 追加属性
     protected $append = [
        'store_ids_text',
        'store_ids_list'
    ];
    
    
    public function getStoreIdsAttr($value, $data)
    {
        // 如果为空直接返回空数组，不做 intval 转换
        return $value ? array_map("intval", explode(',', $data['store_ids'])) : [];
    }

    public function getStoreIdsTextAttr($value, $data)
    {
        if ($data['store_ids']) {
            $store_ids = explode(',', $data['store_ids']);
            $store_ids_text = implode(',', \app\admin\model\shopro\store\Store::where('id', 'in', $store_ids)->column('name'));
        } else {
            $store_ids_text = '全部商家配送门店';
        }
        
        return $store_ids_text;
    }

    public function getStoreIdsListAttr($value, $data)
    {
        $store_list = [];
        if ($data['store_ids']) {
            $store_list = \app\admin\model\shopro\store\Store::where('id', 'in', $data['store_ids'])->field('id, name, address, province_name, city_name, area_name, status')->select();
        }

        return $store_list;
    }

    
}
