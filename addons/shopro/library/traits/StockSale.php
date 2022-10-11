<?php

namespace addons\shopro\library\traits;

use addons\shopro\exception\Exception;
use addons\shopro\model\ActivityGoodsSkuPrice;
use addons\shopro\model\Goods;
use addons\shopro\model\GoodsSkuPrice;
use addons\shopro\model\OrderItem;
use addons\shopro\model\ScoreGoodsSkuPrice;

/**
 * 库存销量
 */
trait StockSale
{
    use StockWarning;

    // cache 正向加销量，添加订单之前拦截
    public function cacheForwardSale($goods_list) {
        try {
            // 记录库存不足，中断的位置
            $break_key = -1;
            foreach ($goods_list as $key => $goods) {
                $detail = $goods['detail'];
                $activity = $detail['activity'];

                // 没有活动，不是秒杀|拼团，或者没有 redis
                if (!$activity || !in_array($activity['type'], ['seckill', 'groupon']) || !$this->hasRedis()) {
                    continue;
                }

                // 实例化 redis
                $redis = $this->getRedis();

                $keys = $this->getKeys([
                    'goods_id' => $detail['id'],
                    'goods_sku_price_id' => $detail['current_sku_price']['id'],
                ], [
                    'activity_id' => $activity['id'],
                    'activity_type' => $activity['type'],
                ]);

                extract($keys);
                
                // 活动商品规格
                $goodsSkuPrice = $redis->HGET($activityHashKey, $goodsSkuPriceKey);
                $goodsSkuPrice = json_decode($goodsSkuPrice, true);
                // 活动商品库存
                $stock = $goodsSkuPrice['stock'] ?? 0;

                // 当前销量 + 购买数量 ，salekey 如果不存在，自动创建
                $sale = $redis->HINCRBY($activityHashKey, $saleKey, $goods['goods_num']);

                if ($sale > $stock) {
                    // 记录中断的位置
                    $break_key = $key;
                    throw new \Exception('活动商品库存不足');
                }
            }
        } catch (\Exception $e) {
            // 将 缓存的 销量减掉
            if ($break_key >= 0) {
                foreach ($goods_list as $key => $goods) {
                    if ($key > $break_key) {        // 上面库存不足中断的位置
                        break;
                    }
                    $detail = $goods['detail'];
                    $activity = $detail['activity'];

                    // 没有活动，不是秒杀|拼团，或者没有 redis
                    if (!$activity || !in_array($activity['type'], ['seckill', 'groupon']) || !$this->hasRedis()) {
                        continue;
                    }

                    // 实例化 redis
                    $redis = $this->getRedis();

                    $keys = $this->getKeys([
                        'goods_id' => $detail['id'],
                        'goods_sku_price_id' => $detail['current_sku_price']['id'],
                    ], [
                        'activity_id' => $activity['id'],
                        'activity_type' => $activity['type'],
                    ]);

                    extract($keys);

                    if ($redis->EXISTS($activityHashKey) && $redis->HEXISTS($activityHashKey, $saleKey)) {
                        $sale = $redis->HINCRBY($activityHashKey, $saleKey, -$goods['goods_num']);
                    }
                }
    
                new Exception('商品库存不足');
            }

            new Exception($e->getMessage());
        }
    }


    // cache 反向减销量，取消订单/订单自动关闭 时候
    public function cacheBackSale($order) {
        $items = OrderItem::where('order_id', $order['id'])->select();

        foreach ($items as $key => $item) {
            $this->cacheBackSaleByItem($item);
        }
    }



    // 真实正向 减库存加销量（支付成功扣库存，加销量）
    public function realForwardStockSale($order) {
        $items = OrderItem::where('order_id', $order['id'])->select();

        foreach ($items as $key => $orderItem) {
            // 增加商品销量
            Goods::where('id', $orderItem->goods_id)->setInc('sales', $orderItem->goods_num);

            $goodsSkuPrice = GoodsSkuPrice::where('id', $orderItem->goods_sku_price_id)->find();
            if ($goodsSkuPrice) {
                $goodsSkuPrice->setDec('stock', $orderItem->goods_num);         // 减少库存
                $goodsSkuPrice->setInc('sales', $orderItem->goods_num);         // 增加销量

                // 库存预警检测
                $this->checkStockWarning($goodsSkuPrice);
            }

            if ($orderItem->item_goods_sku_price_id) {
                if ($order['type'] == 'score') {
                    // 积分商城商品，扣除积分规格库存
                    $itemGoodsSkuPrice = ScoreGoodsSkuPrice::where('id', $orderItem->item_goods_sku_price_id)->find();
                } else {
                    // 扣除活动库存
                    $itemGoodsSkuPrice = ActivityGoodsSkuPrice::where('id', $orderItem->item_goods_sku_price_id)->find();
                }
                
                if ($itemGoodsSkuPrice) {
                    $itemGoodsSkuPrice->setDec('stock', $orderItem->goods_num);     // 减少库存
                    $itemGoodsSkuPrice->setInc('sales', $orderItem->goods_num);     // 增加销量
                }
            }

            // 已经真实减库存 减掉预销量，库存都是在缓存中读取的， 真是减库存，没有减掉 缓存库存，所以不需要减掉预销量， 设置开始的活动不可编辑
            // $this->cacheBackSaleByItem($orderItem);
        }
    }


    // 真实反向 加库存减销量（支付过的现在没有返库存，暂时没用）
    public function realBackStockSale($order)
    {
        $items = OrderItem::where('order_id', $order['id'])->select();

        foreach ($items as $key => $orderItem) {
            // 返还商品销量
            Goods::where('id', $orderItem->goods_id)->setDec('sales', $orderItem->goods_num);

            // 返还规格库存
            $goodsSkuPrice = GoodsSkuPrice::where('id', $orderItem->goods_sku_price_id)->find();
            if ($goodsSkuPrice) {
                $goodsSkuPrice->setInc('stock', $orderItem->goods_num);
                $goodsSkuPrice->setDec('sales', $orderItem->goods_num);
            }

            if ($orderItem->item_goods_sku_price_id) {
                if ($order['type'] == 'score') {
                    // 积分商城商品，扣除积分规格库存
                    $itemGoodsSkuPrice = ScoreGoodsSkuPrice::where('id', $orderItem->item_goods_sku_price_id)->find();
                } else {
                    $itemGoodsSkuPrice = ActivityGoodsSkuPrice::where('id', $orderItem->item_goods_sku_price_id)->find();
                }                   

                if ($itemGoodsSkuPrice) {
                    $itemGoodsSkuPrice->setInc('stock', $orderItem->goods_num);       // 增加库存
                    $itemGoodsSkuPrice->setDec('sales', $orderItem->goods_num);       // 减少销量
                }
            }
        }
    }



    // 通过 OrderItem 减预库存
    private function cacheBackSaleByItem($item)
    {
        // 不是秒杀|拼团，或者 没有配置 redis
        if ((strpos($item['activity_type'], 'groupon') === false && strpos($item['activity_type'], 'seckill') === false) || !$this->hasRedis()) {
            return false;
        }

        // 实例化 redis
        $redis = $this->getRedis();

        $keys = $this->getKeys([
            'goods_id' => $item['goods_id'],
            'goods_sku_price_id' => $item['goods_sku_price_id'],
        ], [
            'activity_id' => $item['activity_id'],
            'activity_type' => $item['activity_type'],
        ]);

        extract($keys);

        if ($redis->EXISTS($activityHashKey) && $redis->HEXISTS($activityHashKey, $saleKey)) {
            $sale = $redis->HINCRBY($activityHashKey, $saleKey, -$item['goods_num']);
        }

        return true;
    }
}
