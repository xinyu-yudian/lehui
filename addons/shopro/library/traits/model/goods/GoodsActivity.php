<?php

namespace addons\shopro\library\traits\model\goods;

use addons\shopro\exception\Exception;
use addons\shopro\library\traits\ActivityCache;
use addons\shopro\model\Goods;
use addons\shopro\model\Activity;
use addons\shopro\model\OrderItem;
use addons\shopro\model\Order;
use think\Cache;
use think\Db;

trait GoodsActivity
{
    use ActivityCache;

    /**
     * 处理商品活动
     *
     * @param [type] $detail
     * @param [type] $sku_price
     * @return void
     */
    public static function operActivity($detail, $sku_price)
    {
        $detail = self::operActivitySkuPrice($detail, $sku_price);

        // 判断，秒杀，拼团，不能和满减，满折同时存在
        if (isset($detail['activity']) && $detail['activity']) {
            // 存在秒杀，拼团，不获取 满减，满包邮
            $activityDiscountType = ['free_shipping'];
        } else {
            $activityDiscountType = [
                'full_reduce',      // 满减
                'full_discount',    // 满折
                'free_shipping'     // 包邮
            ];
        }
        $detail = self::operActivityDiscount($detail, $activityDiscountType);
        
        return $detail;
    }


    /**
     * 处理商品折扣活动
     *
     * @param object $detail     商品详情
     * @param object $activityDiscountType       要查詢的活动类型
     * @return array
     */
    public static function operActivityDiscount($detail, $activityDiscountType = []) {
        $activities = (new self)->getActivityDiscount($detail['id'], $activityDiscountType);

        $activityDiscountsTags = [];
        $activityDiscountsTypes = [];
        foreach ($activities as $key => $activity) {
            $activityDiscountsTypes[] = $activity['type'];
            $activityDiscountsTags[] = $activity['tag'];
        }

        $detail->activity_discounts = $activities;
        $detail->activity_discounts_types = join(',', array_filter(array_unique($activityDiscountsTypes)));
        $detail->activity_discounts_tags = array_filter(array_unique($activityDiscountsTags));
        return $detail;
    }


    /**
     * 处理 秒杀拼团活动
     *
     * @param object $detail
     * @param object $sku_price
     * @return array
     */
    public static function operActivitySkuPrice($detail, $sku_price)
    {
        $activity = (new self)->getActivity($detail['id'], [
            'groupon',
            'seckill'
        ]);

        if (!empty($activity)) {
            switch ($activity['type']) {
                case 'seckill':
                    $activity_goods_sku_price = $activity['activity_goods_sku_price'];
                    $new_sku_price = [];
                    foreach ($sku_price as $s => $k) {
                        $new_sku_price[$s] = $k;
                        $new_sku_price[$s]['stock'] = 0;
                        $new_sku_price[$s]['sales'] = 0;
                        foreach ($activity_goods_sku_price as $c) {
                            if ($k['id'] == $c['sku_price_id']) {
                                // 采用活动的 规格内容
                                $new_sku_price[$s]['stock'] = $c['stock'];
                                $new_sku_price[$s]['sales'] = $c['sales'];
                                $new_sku_price[$s]['price'] = $c['price'];
                                $new_sku_price[$s]['status'] = $c['status'];        // 采用活动的上下架

                                // 记录相关活动类型
                                $new_sku_price[$s]['activity_type'] = $activity['type'];
                                $new_sku_price[$s]['activity_id'] = $activity['id'];
                                // 记录对应活动的规格的记录
                                $new_sku_price[$s]['item_goods_sku_price'] = $c;
                                break;
                            }
                        }
                    }

                    $sku_price = $new_sku_price;
                    break;
                case 'groupon':
                    $activity_goods_sku_price = $activity['activity_goods_sku_price'];
                    $new_sku_price = [];
                    foreach ($sku_price as $s => $k) {
                        $new_sku_price[$s] = $k;
                        $new_sku_price[$s]['stock'] = 0;
                        $new_sku_price[$s]['sales'] = 0;
                        foreach ($activity_goods_sku_price as $c) {
                            if ($k['id'] == $c['sku_price_id']) {
                                // 采用活动的 规格内容
                                $new_sku_price[$s]['stock'] = $c['stock'];
                                $new_sku_price[$s]['sales'] = $c['sales'];
                                $new_sku_price[$s]['groupon_price'] = $c['price'];      // 不覆盖原来规格价格，用作单独购买，讲活动的价格设置为新的拼团价格
                                $new_sku_price[$s]['status'] = $c['status'];        // 采用活动的上下架

                                // 记录相关活动类型
                                $new_sku_price[$s]['activity_type'] = $activity['type'];
                                $new_sku_price[$s]['activity_id'] = $activity['id'];
                                // 记录对应活动的规格的记录（不要了，减小响应包体积, 还得要，下单的时候需要存活动 的 sku_id）
                                $new_sku_price[$s]['item_goods_sku_price'] = $c;
                                break;
                            }
                        }
                    }

                    $sku_price = $new_sku_price;
                    break;
            }

            // 减小响应包体积
            unset($activity['activity_goods_sku_price']);
        }

        // 商品参与的活动
        // 所有的都需要设置一下， 要不然找不到类的属性，如果不存在活动，则都是 null
        $detail->activity = $activity ?: null;
        $detail->activity_type = $activity['type'] ?? null;

        // 移除下架的规格
        foreach ($sku_price as $key => $sku) {
            if ($sku['status'] != 'up') {
                unset($sku_price[$key]);
            }
        }

        $detail->buyers = [];
        if ($activity) {
            if (request()->has('need_buyer') && request()->param('need_buyer')) {
                // 获取当前活动正在买的用户
                $detail->buyers = \addons\shopro\model\Goods::getGoodsBuyers($detail['id'], $activity['id']);
            }

            $prices = array_column($sku_price, 'price');
            $detail['price'] = $prices ? min($prices) : 0;      // min 里面不能是空数组

            if ($activity['type'] == 'groupon') {
                $grouponPrices = array_column($sku_price, 'groupon_price');
                $detail['groupon_price'] = $grouponPrices ? min($grouponPrices) : 0;
            }

            $detail['sales'] = array_sum(array_column($sku_price, 'sales'));
        } else {
            // 正常商品加上显示销量
            $detail['sales'] += $detail['show_sales'];
        }

        $detail['sku_price'] = array_values($sku_price);
        $detail['stock'] = array_sum(array_column($sku_price, 'stock'));

        return $detail;
    }


    /**
     * 获取商品对应的秒杀拼团活动（自动判断查询 redis 或者 数据库）
     *
     * @param [type] $goods_id
     * @param array $activityTypes
     * @return void
     */
    public function getActivity($goods_id, $activityTypes = [])
    {
        if ($this->hasRedis()) {
            // 如果有活动，读取 redis
            $activity = $this->getGoodsActivity($goods_id, $activityTypes);

            return $activity;
        }

        // 没有配置 redis
        $activity = Activity::where('find_in_set(:id,goods_ids)', ['id' => $goods_id])
            ->with(['activityGoodsSkuPrice' => function ($query) use ($goods_id) {
                $query->where('goods_id', $goods_id)
                ->where('status',
                    'up'
                );
            }])
            ->where('type', 'in', $activityTypes)
            ->order('starttime', 'asc')     // 优先查询最先开始的活动（允许商品同时存在多个活动中， 只要开始结束时间不重合）
            ->find();

        return $activity;
    }


    /**
     * (已测试)获取商品对应的促销活动（满减，满赠等，自动判断查询 redis 或者 数据库），只查询进行中的
     *
     * @param [type] $goods_id
     * @param array $activityTypes
     * @param array $type  all：获取当前商品参与的指定类型的所有活动，first：只取第一条
     * @return array
     */
    public function getActivityDiscount($goods_id, $activityTypes = [], $type = 'all')
    {
        if ($this->hasRedis()) {
            // 如果有活动，读取 redis，优惠类活动，自动过滤非活动时间的
            $activity = $this->getGoodsActivityDiscount($goods_id, $activityTypes, $type);
        } else {
            // 没有配置 redis,，这里只查询 进行中 的活动
            $activity = Activity::ing()
                ->where(function ($query) use ($goods_id) {
                    // goods_ids 里面有当前商品，或者 goods_ids 为null(所有商品都参与)
                    $query->where('find_in_set(' . $goods_id . ',goods_ids)')
                        ->whereOr('goods_ids', null);
                })
                ->where('type', 'in', $activityTypes)
                ->{$type == 'all' ? 'select' : 'find'}();
        }

        if ($type == 'all') {
            $newActivity = [];
            foreach ($activity as $act) {
                $newActivity[] = $this->formatActivityDiscount($act);
            }
        } else {
            $newActivity = $this->formatActivityDiscount($activity);
        }

        return $newActivity;
    }



    /**
     * （已测试）格式化促销活动的标签
     *
     * @param [type] $activity
     * @return void
     */
    public function formatActivityDiscount($activity) {
        $rules = $activity['rules'];

        $tags = Activity::formatRuleTags($activity['type'], $rules);
        
        $activity['tag'] = $tags[0] ?? '';
        $activity['tags'] = $tags;

        return $activity;
    }
}