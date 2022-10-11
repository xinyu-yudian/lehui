<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;

/**
 * 库存预警
 */
class StockWarning extends Model
{
    use SoftDelete;

    protected $name = 'shopro_stock_warning';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
    ];

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }


    public function skuPrice()
    {
        return $this->belongsTo(GoodsSkuPrice::class, 'goods_sku_price_id', 'id');
    }
}
