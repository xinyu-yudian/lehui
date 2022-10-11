<?php

namespace addons\shopro\library\traits;

use addons\shopro\exception\Exception;
use addons\shopro\model\StockWarning as StockWarningModel;
use addons\shopro\model\Goods;
use addons\shopro\model\GoodsSkuPrice;

/**
 * 库存预警
 */
trait StockWarning
{
    /**
     * 获取全局库存配置
     *
     * @return void
     */
    public function getStockConfig() {
        $config = json_decode(\addons\shopro\model\Config::cache(60)->where(['name' => 'order'])->value('value'), true);

        $stockConfig = $config && isset($config['goods']) ? $config['goods'] : [];
        if (!isset($stockConfig['stock_warning'])) {
            $stockConfig['stock_warning'] = 0;
        }

        return $stockConfig;
    }


    /**
     * 获取库存预警阀值
     *
     * @param [type] $goodsSkuPrice
     * @return void
     */
    public function getStockWarning($goodsSkuPrice) {
        if (!is_null($goodsSkuPrice['stock_warning'])) {
            $stock_warning = $goodsSkuPrice['stock_warning'];
        } else {
            $stockConfig = $this->getStockConfig();

            $stock_warning = $stockConfig['stock_warning'];
        }

        return $stock_warning;
    }



    /**
     * 检测库存是否低于预警阀值，并且记录
     *
     * @param [type] $goodsSkuPrice
     * @return void
     */
    public function checkStockWarning ($goodsSkuPrice, $type = 'edit') {
        $stock_warning = $this->getStockWarning($goodsSkuPrice);
        // 读取系统配置库存预警值
        if ($goodsSkuPrice['stock'] < $stock_warning) {
            // 增加库存不足记录
            $this->addStockWarning($goodsSkuPrice, $stock_warning);
        } else {
            if ($type == 'edit') {
                // 如果编辑了并且库存大于预警值需要检查并把记录删除
                $this->delStockWarning($goodsSkuPrice['id'], $goodsSkuPrice['goods_id']);
            }
        }
    }


    /**
     * 检测这个商品的所有规格库存预警
     *
     * @param [type] $goodsSkuPrices
     * @return void
     */
    public function checkAllStockWarning($goodsSkuPrices, $type = 'add') {
        foreach ($goodsSkuPrices as $key => $goodsSkuPrice) {
            $this->checkStockWarning($goodsSkuPrice, $type);
        }
    }


    /**
     * 记录库存低于预警值
     *
     * @param [type] $goodsSkuPrice
     * @param [type] $stock_warning
     * @return void
     */
    public function addStockWarning($goodsSkuPrice, $stock_warning) {
        $stockWarning = StockWarningModel::where('goods_sku_price_id', $goodsSkuPrice['id'])
                                    ->where('goods_id', $goodsSkuPrice['goods_id'])->find();

        if ($stockWarning) {
            if ($stockWarning['stock_warning'] != $stock_warning
                || $stockWarning->goods_sku_text != $goodsSkuPrice['goods_sku_text']
                ) {
                $stockWarning->goods_sku_text = is_array($goodsSkuPrice['goods_sku_text']) ? join(',', $goodsSkuPrice['goods_sku_text']) : $goodsSkuPrice['goods_sku_text'];;
                $stockWarning->stock_warning = $stock_warning;

                $stockWarning->save();
            }
        } else {
            $stockWarning = new StockWarningModel();

            $stockWarning->goods_id = $goodsSkuPrice['goods_id'];
            $stockWarning->goods_sku_price_id = $goodsSkuPrice['id'];
            $stockWarning->goods_sku_text = is_array($goodsSkuPrice['goods_sku_text']) ? join(',', $goodsSkuPrice['goods_sku_text']) : $goodsSkuPrice['goods_sku_text'];
            $stockWarning->stock_warning = $stock_warning;
            $stockWarning->save();
        }
        return $stockWarning;
    }


    /**
     * 删除规格预警，比如：多规格编辑之后，作废的规格预警，补充库存之后的规格预警
     *
     * @param array $ids
     * @param integer $goods_id
     * @return void
     */
    public function delStockWarning($goodsSkuPriceIds = [], $goods_id = 0) {
        $goodsSkuPriceIds = is_array($goodsSkuPriceIds) ? $goodsSkuPriceIds : [$goodsSkuPriceIds];

        StockWarningModel::destroy(function ($query) use ($goods_id, $goodsSkuPriceIds) {
            $query->where('goods_id', $goods_id)
                    ->where('goods_sku_price_id', 'in', $goodsSkuPriceIds);
        });
    }


    /**
     * 删除商品除了这些规格之外的规格预警
     *
     * @param array $ids
     * @param integer $goods_id
     * @return void
     */
    public function delNotStockWarning($goodsSkuPriceIds = [], $goods_id = 0) {
        $goodsSkuPriceIds = is_array($goodsSkuPriceIds) ? $goodsSkuPriceIds : [$goodsSkuPriceIds];

        StockWarningModel::destroy(function ($query) use ($goods_id, $goodsSkuPriceIds) {
            $query->where('goods_id', $goods_id)
                    ->where('goods_sku_price_id', 'not in', $goodsSkuPriceIds);
        });
    }
}
