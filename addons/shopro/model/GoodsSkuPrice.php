<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;

/**
 * 商品多规格价格库存模型
 */
class GoodsSkuPrice extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_goods_sku_price';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $hidden = ['createtime', 'updatetime', 'deletetime'];

    // 追加属性
    protected $append = [
        "goods_sku_id_arr"
    ];

    public function getImageAttr($value, $data)
    {
        if (!empty($value)) return cdnurl($value, true);
        return $value;

    }

    // public function getGoodsSkuTextAttr($value, $data)
    // {
    //     $goods_sku_ids = $data['goods_sku_ids'];
    //     $goods_sku_ids_array = explode(',', $goods_sku_ids);
    //     $text = '';
    //     foreach ($goods_sku_ids_array as $v) {
    //         $goodsSku = GoodsSku::get($v);
    //         if ($goodsSku) {
    //             $text .= ' ' . $goodsSku->name;
    //         }
    //     }
    //     return $text;
    // }


    public function getGoodsSkuIdArrAttr($value, $data)
    {
        return explode(',', $data['goods_sku_ids']);
    }

}
