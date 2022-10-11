<?php

namespace addons\shopro\library\traits;

use addons\shopro\exception\Exception;
use addons\shopro\library\Redis;
use addons\shopro\model\Activity;
use addons\shopro\model\ActivityGoodsSkuPrice;
use addons\shopro\model\Goods;
use addons\shopro\model\GoodsSkuPrice;
use addons\shopro\model\OrderItem;
use addons\shopro\model\ScoreGoodsSkuPrice;

/**
 * 活动 redis 缓存
 */
trait ActivityCache
{
    protected $zsetKey = 'zset-activity';
    protected $hashPrefix = 'hash-activity:';
    protected $hashGoodsPrefix = 'goods-';
    protected $hashGrouponPrefix = 'groupon-';


    public function hasRedis($is_interrupt = false) {
        $error_msg = '';
        try {
            $redis = $this->getRedis();

            // 检测连接是否正常
            $redis->ping();
        } catch (\BadFunctionCallException $e) {
            // 缺少扩展
            $error_msg = $e->getMessage() ? $e->getMessage() : "缺少 redis 扩展";
        } catch (\RedisException $e) {
            // 连接拒绝
            \think\Log::write('redis connection redisException fail: ' . $e->getMessage());
            $error_msg = $e->getMessage() ? $e->getMessage() : "redis 连接失败";
        } catch (\Exception $e) {
            // 异常
            \think\Log::write('redis connection fail: ' . $e->getMessage());
            $error_msg = $e->getMessage() ? $e->getMessage() : "redis 连接异常";
        }

        if ($error_msg) {
            if ($is_interrupt) {
                throw new \Exception($error_msg);
            } else {
                return false;
            }
        }

        return true;
    }


    public function getRedis() {
        if (!isset($GLOBALS['SPREDIS'])) {
            $GLOBALS['SPREDIS'] = (new Redis())->getRedis();
        }

        return $GLOBALS['SPREDIS'];
    }


    /**
     * 将活动设置到 redis 中
     *
     * @param [type] $activity
     * @param array $goodsList
     * @return void
     */
    public function setActivity($activity, $goodsList = []) {
        $redis = $this->getRedis();

        // hash 键值
        $hashKey = $this->getHashKey($activity['id'], $activity['type']);

        // 删除旧的可变数据，需要排除销量 key 
        if ($redis->EXISTS($hashKey)) {
            // 如果 hashKey 存在,删除规格
            $hashs = $redis->HGETALL($hashKey);

            foreach ($hashs as $hashField => $hashValue) {
                // 是商品规格，并且不是销量
                if (strpos($hashField, $this->hashGoodsPrefix) !== false && strpos($hashField, '-sale') === false) {
                    // 商品规格信息，删掉
                    $redis->HDEL($hashKey, $hashField);
                }
            }
        }

        $redis->HMSET($hashKey, [
                            'id' => $activity['id'],
                            'title' => $activity['title'],
                            'type' => $activity['type'],
                            'richtext_id' => $activity['richtext_id'],
                            'richtext_title' => $activity['richtext_title'],
                            'starttime' => $activity['starttime'],
                            'endtime' => $activity['endtime'],
                            'rules' => is_array($activity['rules']) ? json_encode($activity['rules']) : $activity['rules'],
                            'goods_ids' => $activity['goods_ids']
                        ]
                    );

        if (in_array($activity['type'], ['groupon', 'seckill'])) {
            // 拼团或者秒杀，记录商品规格信息 （里面包含活动库存，价格等信息）
            foreach ($goodsList as $goods) {
                unset($goods['sales']);     // 规格销量单独字段保存 goods-id-id-sale key
                $goods_sku_key = $this->getHashGoodsKey($goods['goods_id'], $goods['sku_price_id']);
                // 获取当前规格的销量，修改库存的时候，需要把 stock 加上这部分销量
                $cacheSale = $redis->HGET($hashKey, $goods_sku_key . '-sale');
                $goods['stock'] = $goods['stock'] + $cacheSale;
                $redis->HSET($hashKey, $this->getHashGoodsKey($goods['goods_id'], $goods['sku_price_id']), json_encode($goods));
            }
        }

        // 将 hash 键值存入 有序集合，score 为 id
        $redis->ZADD($this->zsetKey, $activity['starttime'], $hashKey);
    }


    /**
     * 获取所有活动(前端：秒杀商品列表，拼团商品列表)
     *
     * @param array $activityTypes 为空将查询所有类型的活动
     * @param string $status
     * @param string $format_type       // 格式化类型，默认clear,清理多余的字段，比如拼团的 团信息
     * @return void
     */
    public function getActivityList($activityTypes = [], $status = 'all', $format_type = 'normal') {
        $redis = $this->getRedis();

        // 获取对应的活动类型的集合
        $activityHashList = $this->getActivityHashKeysByType($activityTypes);
        
        $activityList = [];
        if (!$activityHashList) {       // 没有获取到，返回空数组
            return $activityList;
        }

        foreach ($activityHashList as $activityHashKey) {
            // 查询活动状态
            if ($status != 'all') {
                $activity_status = $this->getActivityStatusByHashKey($activityHashKey);
                
                if ($status != $activity_status) {
                    continue;
                }
            }

            // 格式化活动
            $activity = $this->formatActivityByType($activityHashKey, $format_type);
            if ($activity) {
                $activityList[] = $activity;
            }
        }

        return $activityList;
    }


    /**
     * 查询商品列表,详情时，获取这个商品对应的秒杀拼团等活动
     *
     * @param [type] $goods_id
     * @param Array $activityTypes
     * @param integer $activity_id
     * @return void
     */
    public function getGoodsActivity($goods_id, $activityTypes = [], $activity_id = 0) {
        // 获取商品第一条活动的 hash key
        $activityHashKey = $this->getActivityHashKeyByGoods($goods_id, $activityTypes, 'first', $activity_id);

        // 如果存在活动
        if ($activityHashKey) {
            // 获取活动并且按照商品的要求格式化
            return $this->formatActivityByType($activityHashKey, 'goods', ['goods_id', $goods_id]);
        }

        return null;
    }


    /**
     * 查询商品列表,详情时，获取这个商品对应的满减，满折等活动
     *
     * @param int $goods_id 特定商品 id
     * @param Array $activityTypes  要查询的活动数组
     * @param string $type  查单条，还是全部 all|first
     * @param int $activity_id
     * @return void
     */
    public function getGoodsActivityDiscount($goods_id, $activityTypes = [], $type = 'all', $activity_id = 0) {
        // 获取活动的 hash key
        $activityHashKey = $this->getActivityHashKeyByGoods($goods_id, $activityTypes, $type, $activity_id);

        // 如果存在活动
        if ($activityHashKey) {
            if (is_array($activityHashKey)) {
                $activities = [];
                foreach ($activityHashKey as $key => $hashKey) {
                    $activity = $this->formatActivityByType($hashKey, 'discount');
                    if ($activity) {
                        $activities[] = $activity;
                    }
                }

                return $activities;
            } else {
                // 获取活动所有信息
                return $activity = $this->formatActivityByType($activityHashKey, 'discount');
            }
        }

        return $type == 'all' ? [] : null;
    }


    /**
     * 通过活动的键值，获取活动完整信息
     *
     * @param [type] $activityHashKey
     * @return array
     */
    public function getActivityByHashKey($activityHashKey)
    {
        $redis = $this->getRedis();

        // 取出整条 hash 记录
        $activityHash = $redis->HGETALL($activityHashKey);

        return $activityHash;
    }


    // 删除活动缓存
    public function delActivity($activity) {
        $redis = $this->getRedis();

        $hashKey = $this->getHashKey($activity['id'], $activity['type']);

        // 删除 hash
        $redis->DEL($hashKey);

        // 删除集合
        $redis->ZREM($this->zsetKey, $hashKey);
    }


    /**
     * 通过商品获取该商品参与的活动的hash key
     *
     * @param [type] $goods_id
     * @param Array $activityType
     * @param string $type      全部还是第一条
     * @param integer $activity_id
     * @return void
     */
    private function getActivityHashKeyByGoods($goods_id, $activityType = [], $type = 'first', $activity_id = 0) {
        $redis = $this->getRedis();

        // 获取对应类型的活动集合
        $activityHashList = $this->getActivityHashKeysByType($activityType, $activity_id);

        $activityHashKeys = [];
        if (!$activityHashList) {       // 没有获取到，返回空数组
            return $activityHashKeys;
        }

        foreach ($activityHashList as $activityHashKey) {
            if (strpos($activityHashKey, 'seckill') === false && strpos($activityHashKey, 'groupon') === false) {
                // 不是拼团，秒杀，要校验活动时间，如果不在活动时间，跳过
                // 获取活动状态
                $activity_status = $this->getActivityStatusByHashKey($activityHashKey);
                if ($activity_status != 'ing') {
                    continue;
                }
            }

            // 判断这条活动是否包含该商品
            $goods_ids = array_filter(explode(',', $redis->HGET($activityHashKey, 'goods_ids')));
            if (in_array($goods_id, $goods_ids) || empty($goods_ids)) {
                $activityHashKeys[] = $activityHashKey;

                if ($type == 'first') {     // 只取第一条
                    break;
                }
            }
        }

        if ($activity_id && !$activityHashKeys) {
            // 查询特定活动，没找到，抛出异常， 活动不存在
            new Exception('活动不存在');
        }

        return $type == 'first' ? ($activityHashKeys[0] ?? '') : $activityHashKeys;
    }



    /**
     * 获取活动的状态
     */
    private function getActivityStatusByHashKey($activityHashKey) {
        $redis = $this->getRedis();

        $starttime = $redis->HGET($activityHashKey, 'starttime');
        $endtime = $redis->HGET($activityHashKey, 'endtime');

        if ($starttime < time() && $endtime > time()) {
            $status = 'ing';
        }else if ($starttime > time()) {
            $status = 'nostart';
        }else if ($endtime < time()) {
            $status = 'ended';
        }

        return $status;
    }


    /**
     * 获取活动类型数组的所有活动hashkeys
     *
     * @param array|string $activityTypes
     * @return array
     */
    private function getActivityHashKeysByType($activityTypes = [], $activity_id = 0) {
        $redis = $this->getRedis();

        $activityTypes = is_array($activityTypes) ? $activityTypes : [$activityTypes];
        $activityTypes = array_values(array_filter($activityTypes));  // 过滤空值
        
        // 获取活动集合
        $hashList = $redis->ZRANGE($this->zsetKey, 0, 999999999);

        // 优先判断 activity_id,可以唯一确定 活动key, 不需要判断 activityTypes
        if ($activity_id) {
            $activityHashKeys = [];
            foreach ($hashList as $hashKey) {
                $suffix = ':' . $activity_id;
                // 判断是否是要找的活动id, 截取 hashKey 后面几位，是否为当前要查找的活动 id
                if (substr($hashKey, (strlen($hashKey) - strlen($suffix))) == $suffix) {
                    $activityHashKeys[] = $hashKey;
                    break;
                }
            }

            return $activityHashKeys;
        }

        // 判断是否传入了 需要的活动类型，默认取全部活动
        if ($activityTypes) {
            // 获取对应的活动类型的集合
            $activityHashKeys = [];

            foreach ($hashList as $hashKey) {
                // 循环要查找的活动类型数组
                foreach ($activityTypes as $type) {
                    if (strpos($hashKey, $type) !== false) {        // 是要查找的类型
                        $activityHashKeys[] = $hashKey;
                        break;
                    }
                }
            }
        } else {
            // 全部活动
            $activityHashKeys = $hashList;
        }

        return $activityHashKeys;
    }


    // ------------------------格式化活动---------------------

    /**
     * 格式化活动
     *
     * @param string array $activityHash 活动 key 或者活动完整信息
     * @param string $type  格式化方式
     * @param array $data  额外参数
     * @return void
     */
    public function formatActivityByType($activityHash, $type = 'normal', $data = []) {
        switch($type) {
            case 'normal' :
                // 正常模式，只移除销量，团信息，保留全部商品规格数据
                $activity = $this->getActivityFormatNormal($activityHash, $data);
                break;
            case 'clear' :
                // 简洁模式，只保留活动表基本信息
                $activity = $this->getActivityFormatClear($activityHash, $data);
                break;
            case 'goods' :
                // 按照前端商品方式格式化
                $activity = $this->getActivityFormatGoods($activityHash, $data);
                break;
            case 'discount' :
                $activity = $this->getActivityFormatDiscount($activityHash, $data);
                break;
            default :
                $activity = $this->getActivityFormatNormal($activityHash, $data);
                break;
        }

        return $activity;
    }


    /**
     * 正常模式，只移除销量， 团信息，保留全部商品规格数据
     *
     * @param string $activityHashKey
     * @param array $data  额外数据，商品 id
     * @return void
     */
    private function getActivityFormatNormal($activityHashKey, $data = []) {
        // 传入的是活动的key
        $activityHash = $this->getActivityByHashKey($activityHashKey);

        $activity = [];

        foreach ($activityHash as $key => $value) {
            // 包含 -sale 全部跳过
            if (strpos($key, '-sale') !== false) {
                continue;
            } else if (strpos($key, $this->hashGrouponPrefix) !== false) {
                // 拼团的参团人数，团用户，移除
                continue;
            } else if ($key == 'rules') {
                $activity[$key] = json_decode($value, true);
            } else {
                // 普通键值
                $activity[$key] = $value;
            }
        }

        if ($activity) {
            // 处理活动状态
            $activity['status_code'] = Activity::getStatusCode($activity);
        }

        return $activity ?: null;
    }


    /**
     * 简洁模式，只保留活动表基本信息
     *
     * @param string $activityHashKey
     * @param array $data  额外数据，商品 id
     * @return void
     */
    private function getActivityFormatClear($activityHashKey, $data = []) {
        $activityHash = $this->getActivityByHashKey($activityHashKey);

        $activity = [];

        foreach ($activityHash as $key => $value) {
            // 包含 -sale 全部跳过
            if (strpos($key, $this->hashGoodsPrefix) !== false) {
                continue;
            } else if (strpos($key, $this->hashGrouponPrefix) !== false) {
                // 拼团的参团人数，团用户，移除
                continue;
            } else if ($key == 'rules') {
                $activity[$key] = json_decode($value, true);
            } else {
                // 普通键值
                $activity[$key] = $value;
            }
        }

        if ($activity) {
            // 处理活动状态
            $activity['status_code'] = Activity::getStatusCode($activity);
        }

        return $activity ?: null;
    }


    /**
     * 获取并按照商品展示格式化活动数据
     *
     * @param string $activityHashKey hash key
     * @param array $data  额外数据，商品 id
     * @return array
     */
    private function getActivityFormatGoods($activityHashKey, $data = [])
    {
        $goods_id = $data['goods_id'] ?? 0;
        // 传入的是活动的key
        $activityHash = $this->getActivityByHashKey($activityHashKey);

        $activity = [];

        // 商品前缀
        $goodsPrefix = $this->hashGoodsPrefix . ($goods_id ? $goods_id . '-' : '');

        foreach ($activityHash as $key => $value) {
            // 包含 -sale 全部跳过
            if (strpos($key, '-sale') !== false) {
                continue;
            } else if (strpos($key, $goodsPrefix) !== false) {
                // 商品规格信息，或者特定商品规格信息
                $goods = json_decode($value, true);

                // 计算销量库存数据
                $goods = $this->calcGoods($goods, $activityHashKey);

                // 商品规格项
                $activity['activity_goods_sku_price'][] = $goods;
            } else if ($goods_id && strpos($key, $this->hashGoodsPrefix) !== false) {
                // 需要特定商品时，移除别的非当前商品的数据
                continue;
            } else if (strpos($key, $this->hashGrouponPrefix) !== false) {
                // 拼团的参团人数，团用户，移除
                continue;
            } else if ($key == 'rules') {
                $activity[$key] = json_decode($value, true);
            } else {
                // 普通键值
                $activity[$key] = $value;
            }
        }

        if ($activity) {
            // 处理活动状态
            $activity['status_code'] = Activity::getStatusCode($activity);
        }

        return $activity ?: null;
    }


    /**
     * 获取并按照折扣格式展示格式化活动数据
     *
     * @param string $activityHashKey hash key
     * @param array $data  额外数据
     * @return void
     */
    private function getActivityFormatDiscount($activityHashKey, $data = [])
    {
        $activityHash = $this->getActivityByHashKey($activityHashKey);

        $activity = [];
        foreach ($activityHash as $key => $value) {
            if ($key == 'rules') {
                $rules = json_decode($value, true);

                // 存在折扣
                if (isset($rules['discounts']) && $rules['discounts']) {
                    // 处理展示优惠，full 从小到大
                    $discounts = $rules['discounts'] ?? [];

                    $discountsKeys = array_column($discounts, null, 'full');
                    ksort($discountsKeys);
                    $rules['discounts'] = array_values($discountsKeys);        // 优惠按照 full 从小到大排序
                }

                $activity[$key] = $rules;
            } else {
                // 普通键值
                $activity[$key] = $value;
            }
        }

        if ($activity) {
            // 处理活动状态
            $activity['status_code'] = Activity::getStatusCode($activity);
        }

        return $activity ?: null;
    }


    /**
     * 计算每个规格的真实库存、销量
     *
     * @param [type] $goods
     * @param [type] $activityHashKey
     * @return void
     */
    private function calcGoods($goods, $activityHashKey)
    {
        $redis = $this->getRedis();

        // 销量 key 
        $saleKey = $this->getHashGoodsKey($goods['goods_id'], $goods['sku_price_id'], true);

        // 缓存中的销量
        $cacheSale = $redis->HGET($activityHashKey, $saleKey);

        $stock = $goods['stock'] - $cacheSale;
        $goods['stock'] = $stock > 0 ? $stock : 0;
        $goods['sales'] = $cacheSale;

        return $goods;
    }



    // 拼接 hash key
    private function getHashKey($activity_id, $activity_type) {
        // 示例 hash-activity:groupon:25
        return $this->hashPrefix . $activity_type . ':' . $activity_id;
    }


    // 拼接 hash 表中 goods 的 key
    private function getHashGoodsKey($goods_id, $sku_price_id, $is_sale = false)
    {
        // 示例 商品规格：goods-25-30 or 商品规格销量：goods-25-30-sale
        return $this->hashGoodsPrefix . $goods_id . '-' . $sku_price_id . ($is_sale ? '-sale' : '');
    }



    // 拼接 hash 表中 groupon 的 key
    private function getHashGrouponKey($groupon_id, $goods_id, $type = '')
    {
        return $this->hashGrouponPrefix . $groupon_id . '-' . $goods_id . ($type ? '-' . $type : '');
    }


    // 获取 key 集合
    public function getKeys($detail, $activity)
    {
        // 获取 hash key
        $activityHashKey = $this->getHashKey($activity['activity_id'], $activity['activity_type']);

        $goodsSkuPriceKey = '';
        $saleKey = '';
        if (isset($detail['goods_sku_price_id']) && $detail['goods_sku_price_id']) {
            // 获取 hash 表中商品 sku 的 key
            $goodsSkuPriceKey = $this->getHashGoodsKey($detail['goods_id'], $detail['goods_sku_price_id']);
            // 获取 hash 表中商品 sku 的 销量的 key
            $saleKey = $this->getHashGoodsKey($detail['goods_id'], $detail['goods_sku_price_id'], true);
        }

        // 需要拼团的字段
        $grouponKey = '';
        $grouponNumKey = '';
        $grouponUserlistKey = '';
        if (isset($detail['groupon_id']) && $detail['groupon_id']) {
            // 获取 hash 表中团 key
            $grouponKey = $this->getHashGrouponKey($detail['groupon_id'], $detail['goods_id']);
            // 获取 hash 表中团当前人数 key
            $grouponNumKey = $this->getHashGrouponKey($detail['groupon_id'], $detail['goods_id'], 'num');
            // 获取 hash 表中团当前人员列表 key
            $grouponUserlistKey = $this->getHashGrouponKey($detail['groupon_id'], $detail['goods_id'], 'userlist');
        }

        return compact('activityHashKey', 'goodsSkuPriceKey', 'saleKey', 'grouponKey', 'grouponNumKey', 'grouponUserlistKey');
    }
}
