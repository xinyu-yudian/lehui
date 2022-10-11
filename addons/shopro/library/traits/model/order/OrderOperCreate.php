<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\exception\Exception;
use addons\shopro\model\ScoreGoodsSkuPrice;
use addons\shopro\model\Goods;
use addons\shopro\model\User;
use addons\shopro\model\UserAddress;
use addons\shopro\model\UserCoupons;
use addons\shopro\model\Dispatch;
use think\Cache;
use think\Db;

trait OrderOperCreate
{

    public static $calc_type = 'pre';
    public static $msg = null;

    /**
     * 获取请求参数，初始化，并设置默认值
     *
     * @param array $params
     * @return array
     */
    public static function preParams($params)
    {
        extract($params);
        $order_type = $order_type ?? 'goods';
        $groupon_id = $groupon_id ?? 0;        // 拼团的 团 id
        $buy_type = $buy_type ?? 'alone';        // 拼团的 购买方式: alone=单独购买,groupon=开团

        return compact(
            "goods_list",
            "order_type",
            "groupon_id",
            "buy_type",
            "address_id",
            "coupons_id",
            "from"
        );
    }


    /**
     * 判断并处理异常
     *
     * @param string $msg 处理结果
     * @param boolean $force 是否强制抛出异常
     * @return boolean
     */
    public static function checkAndException($msg, $force = false)
    {
        if (self::$calc_type == 'create' || $force) {
            // 如果是创建订单，或者强制抛出异常
            new Exception($msg);
        } else {
            // 预下单，记录第一条错误信息
            if (!self::$msg) {
                self::$msg = $msg;
            }
        }

        return false;
    }


    /**
     * 下单前检测，商品状态，秒杀，拼团活动状态，必要的选择项（比如下单收货地址），收货地址等
     *
     * @param array $params，请求参数
     * @return array
     */
    public static function preCheck($params)
    {
        $user = User::info();

        // 获取请求参数
        extract(self::preParams($params));

        $activity_type = '';
        $new_goods_list = [];
        $activity_discounts = [];
        foreach ($goods_list as $key => $buyinfo) {
            // 最少购买一件
            $buyinfo['goods_num'] = intval($buyinfo['goods_num']) < 1 ? 1 : intval($buyinfo['goods_num']);

            $sku_price_id = $buyinfo['sku_price_id'];
            $activity = null;

            if ($order_type == 'score') {
                // 积分商城商品详情
                $detail = ScoreGoodsSkuPrice::getGoodsDetail($buyinfo['goods_id']);
            } else {
                $detail = Goods::getGoodsDetail($buyinfo['goods_id']);
                // 如果有活动，判断活动是否正在进行中
                if (isset($detail['activity']) && $detail['activity']) {
                    $activity = $detail['activity'];
                    if ($activity['starttime'] > time()) {
                        self::checkAndException('商品活动未开始');
                    }

                    if ($activity['endtime'] < time()) {
                        self::checkAndException('商品活动已结束');
                    }
                }

                if (isset($detail['activity_discounts']) && $detail['activity_discounts']) {
                    $activity_discounts = array_merge($activity_discounts, $detail['activity_discounts']);
                }
            }

            $sku_prices = $detail['sku_price'];
            foreach ($sku_prices as $key => $sku_price) {
                if ($sku_price['id'] == $sku_price_id) {
                    $detail->current_sku_price = $sku_price;
                    break;
                }
            }

            if (!$detail || ($order_type != 'score' && $detail->status === 'down')) {
                self::checkAndException('商品不存在或已下架', true);
            }

            if (!isset($detail->current_sku_price) || !$detail->current_sku_price) {
                self::checkAndException('商品规格不存在', true);
            }

            // 判断商品是否选择了配送方式
            if (!isset($buyinfo['dispatch_type']) || empty($buyinfo['dispatch_type'])) {
                // 不存在，或者为空，默认获第一个
                $current_dispatch_type = array_filter(explode(',', $detail['dispatch_type']));
                $buyinfo['dispatch_type'] = $current_dispatch_type[0] ?? '';
            }

            if (self::$calc_type == 'create') {
                if (empty($buyinfo['dispatch_type'])) {
                    new Exception("请选择配送方式");
                }

                if ($buyinfo['dispatch_type'] == 'selfetch' && (!isset($buyinfo['store_id']) || !$buyinfo['store_id'])) {
                    new Exception("请选择自提点");
                }
            }

            // 组装 商品详情
            $buyinfo['detail'] = $detail;
            $new_goods_list[] = $buyinfo;

            // 要购买的数量
            $need_goods_num = $buyinfo['goods_num'];
            // 开团需要的最小库存
            $groupon_num = 0;
            // 是否允许单独购买
            $is_alone = 1;
            if (isset($detail['activity_type']) && $detail['activity_type']) {
                $activity_type .= $detail['activity_type'] . ',';

                if ($detail['activity_type'] == 'groupon') {
                    // 拼团
                    $rules = $activity['rules'];
                    $is_alone = $rules['is_alone'] ?? 1;
                    // 成团人数
                    $num = $rules['team_num'] ?? 1;

                    // 要单独购买
                    if ($buy_type == 'alone') {
                        // 不允许单独购买
                        if (!$is_alone) {
                            self::checkAndException('该商品不允许单独购买');
                        }
                    } else {
                        // 拼团，临时将拼团价设置为商品价格
                        $detail->current_sku_price->price = $detail->current_sku_price->groupon_price;
                    }

                    // 如果是开新团
                    if (!$groupon_id && $buy_type == 'groupon') {
                        // 开团需要的最小库存
                        $groupon_num = ($num - 1);
                    }
                }
            }

            // 当前库存，小于要购买的数量 + 开团人数
            if ($detail->current_sku_price['stock'] < ($buyinfo['goods_num'] + $groupon_num)) {
                if ($detail->current_sku_price['stock'] < $buyinfo['goods_num']) {
                    // 不够自己买
                    self::checkAndException('商品库存不足');
                } else if ($groupon_num && $is_alone && !$groupon_id && $buy_type == 'groupon') {
                    // 够自己买，但不够开新团，并且允许单独购买，提醒可以单独购买
                    self::checkAndException('商品库存不足以开团，请选择单独购买');
                }
            }
        }

        if (!count($new_goods_list)) {
            self::checkAndException('请选择要购买的商品');
        }

        if (strpos($activity_type, 'seckill') !== false && count($new_goods_list) > 1) {
            self::checkAndException('秒杀商品必须单独购买');
        }
        if (strpos($activity_type, 'groupon') !== false && count($new_goods_list) > 1) {
            self::checkAndException('拼团商品必须单独购买');
        }
        if ($order_type == 'score' && count($new_goods_list) > 1) {
            self::checkAndException('积分商品必须单独购买');
        }

        $need_address = 1;
        $user_address = null;
        // 判断是否有需要收货地址的商品，新版每个商品需要选择配送方式
        $dispatchTypes = array_column($new_goods_list, 'dispatch_type');
        if (in_array('express', $dispatchTypes) || in_array('store', $dispatchTypes)) {
            // 配送方式为 快递 或者 自提 必须填写收货地址
            $user_address = UserAddress::where("user_id", $user->id)->find($address_id);

            if (is_null($user_address) && self::$calc_type == 'create') {
                new Exception("请选择正确的收货地址");
            }
        } else {
            // 不需要收货地址
            $need_address = 0;
        }

        return [
            $new_goods_list,
            $activity_type,
            $activity_discounts,
            $need_address,
            $user_address
        ];
    }


    /**
     * 计算订单各种费用
     *
     * @param array $new_goods_list
     * @param array $user_address
     * @return array
     */
    public static function preCalcAmount($params, $new_goods_list, $user_address)
    {
        // 获取请求参数
        extract(self::preParams($params));

        $goods_original_amount = 0;         // 商品原始总价
        $goods_amount = 0;                  // 商品总价
        $dispatch_amount = 0;               // 运费总价
        $score_amount = 0;                  // 订单总积分

        // 计算商品金额
        foreach ($new_goods_list as $key => $buyinfo) {
            $detail = $buyinfo['detail'];

            // 当前商品原始总价
            $current_goods_original_amount = bcmul($detail->original_price, $buyinfo['goods_num'], 2);
            $goods_original_amount = bcadd($goods_original_amount, $current_goods_original_amount, 2);

            // 当前商品现在总价
            $current_goods_amount = bcmul($detail->current_sku_price->price, $buyinfo['goods_num'], 2);
            $goods_amount = bcadd($goods_amount, $current_goods_amount, 2);

            // 获取配送数据
            if ($buyinfo['dispatch_type']) {
                try {
                    // 捕获里面的异常，然后使用封装的异常处理
                    $dispatchData = Dispatch::getDispatch($buyinfo['dispatch_type'], $detail, [
                        'address' => $user_address,
                        'goods_num' => $buyinfo['goods_num'],
                    ]);
                } catch (\Exception $e) {
                    $message = $e->getMessage();
                    if (!$message) {
                        $result = [];
                        if (method_exists($e, 'getResponse')) {
                            $result = $e->getResponse()->getData();
                        }
                        $message = $result['msg'] ?? '处理失败';
                    }
                    self::checkAndException($message);
                }
            }

            // 配送模板 id
            $current_dispatch_id = $dispatchData['dispatch_id'] ?? 0;
            // 配送费
            $current_dispatch_amount = $dispatchData['dispatch_amount'] ?? 0;
            // 如果商家配送，默认选择最近的门店
            $current_store_id = $buyinfo['store_id'] ?? 0;
            if (
                $buyinfo['dispatch_type'] == 'store'
                && isset($dispatchData['store'])
                && $dispatchData['store']
            ) {
                // 商家配送,自动采用最近的 store
                $current_store_id = $dispatchData['store']['id'];
            }

            // 选用商品中最高运费，暂时，下面会根据包邮活动的结果，再计算一次
            $bcres = bccomp($dispatch_amount, $current_dispatch_amount, 2);     // 比较两个运费
            $dispatch_amount = ($bcres == 1 || $bcres == 0) ? $dispatch_amount : $current_dispatch_amount;      // 取用最大的

            // 当前商品所需积分
            $current_score_amount = 0;
            if ($order_type == 'score') {       // 积分商城规格
                $current_score_amount = ($detail->current_sku_price->score * $buyinfo['goods_num']);
                $score_amount += $current_score_amount;
            }

            // 将计算好的属性记录下来，插入订单 item 表使用
            $new_goods_list[$key]['goods_original_amount'] = $current_goods_original_amount;
            $new_goods_list[$key]['goods_amount'] = $current_goods_amount;
            $new_goods_list[$key]['dispatch_amount'] = $current_dispatch_amount;
            $new_goods_list[$key]['score_amount'] = $current_score_amount;
            $new_goods_list[$key]['dispatch_id'] = $current_dispatch_id;
            $new_goods_list[$key]['store_id'] = $current_store_id;
            $new_goods_list[$key]['activity_type'] = '';
            $new_goods_list[$key]['discount_fee'] = 0;  // 初始化每个商品分配到的优惠
        }

        return [
            $new_goods_list,                // 新的商品列表
            $goods_original_amount,         // 商品原始总价
            $goods_amount,                  // 商品总价
            $dispatch_amount,               // 运费总价
            $score_amount                   // 订单总积分
        ];
    }


    /**
     * 计算商品的优惠，优惠促销 和 优惠券
     *
     * @param array $new_goods_list
     * @param array $activity_discounts
     * @param string $activity_type
     * @param string $dispatch_amount
     * @param array $user_address   用户收货地址
     * @return array
     */
    public static function preCalcDiscount(
        $params,
        $new_goods_list,
        $activity_discounts,
        $activity_type,
        $goods_amount,
        $dispatch_amount,
        $user_address
    ) {
        // 获取请求参数
        extract(self::preParams($params));

        $activity_discount_infos = [];      // 参与的所有促销活动信息
        $activity_discount_money = 0;       // 促销活动金额
        $dispatch_discount_money = 0;       // 邮费实际优惠金额
        $free_shipping_goods_ids = [];         // 所有参与包邮的商品的 id

        $discounts = [];
        // 过滤重复活动
        foreach ($activity_discounts as $activity_discount) {
            if (!isset($discounts[$activity_discount['id']])) {
                $discounts[$activity_discount['id']] = $activity_discount;
            }
        }

        // 将购买的商品，按照活动分类
        foreach ($new_goods_list as $new_goods) {
            $detail = $new_goods['detail'];
            unset($new_goods['detail']);
            if (isset($detail['activity_discounts']) && $detail['activity_discounts']) {
                foreach ($detail['activity_discounts'] as $ad) {
                    $discounts[$ad['id']]['goods'][] = $new_goods;
                }
            }
        }

        // 计算各个活动是否满足
        foreach ($discounts as $key => $discount) {
            if (!isset($discount['goods'])) {
                // 活动没有商品，直接 next
                continue;
            }

            $discount_total_money = 0;          // 该活动中商品的总价
            $discount_total_num = 0;            // 该活动商品总件数
            $goodsIds = [];                     // 该活动中所有的商品 id
            $current_discount_dispatch_amount = 0;  // 该活动中最高运费

            // 活动中的商品总金额，总件数，所有商品 id
            foreach ($discount['goods'] as $goods) {
                $discount_total_money = bcadd($discount_total_money, $goods['goods_amount'], 2);
                $discount_total_num = bcadd($discount_total_num, $goods['goods_num'], 2);
                $goodsIds[] = $goods['goods_id'];

                $bcres = bccomp($current_discount_dispatch_amount, $goods['dispatch_amount'], 2);     // 比较两个运费
                $current_discount_dispatch_amount = ($bcres == 1 || $bcres == 0) ? $current_discount_dispatch_amount : $goods['dispatch_amount'];      // 取用最大的
            }

            $rules = $discount['rules'];
            // 是按金额，还是按件数比较
            $compareif = $rules['type'] == 'num' ? 'discount_total_num' : 'discount_total_money';

            if (in_array($discount['type'], ['full_reduce', 'full_discount'])) {
                // 将规则按照从大到校排列,优先比较是否满足最大规则
                $rules_discounts = isset($rules['discounts']) && $rules['discounts'] ? array_reverse($rules['discounts']) : [];    // 数组反转

                // 满减， 满折多个规则从大到小匹配最优惠
                foreach ($rules_discounts as $d) {
                    if (${$compareif} < $d['full']) {
                        // 不满足条件，接着循环下个规则
                        continue;
                    }

                    // 满足优惠
                    if ($discount['type'] == 'full_reduce') {
                        $current_activity_discount_money = (isset($d['discount']) && $d['discount']) ? $d['discount'] : 0;
                    } else {
                        $dis = bcdiv($d['discount'], 10, 3);        // 保留三位小数，转化折扣
                        $dis = $dis > 1 ? 1 : ($dis < 0 ? 0 : $dis);    // 定义边界 0 - 1
                        $activity_dis = 1 - $dis;
                        $current_activity_discount_money = round(bcmul($discount_total_money, $activity_dis, 3), 2);       // 计算折扣金额,四舍五入
                    }

                    // 记录该活动的一些统计信息
                    $activity_discount_infos[] = [
                        'activity_id' => $discount['id'],                           // 活动id
                        'activity_title' => $discount['title'],                     // 活动标题
                        'activity_type' => $discount['type'],                       // 活动类型
                        'activity_discount_money' => $current_activity_discount_money,      // 优惠金额
                        'rule_type' => $rules['type'],                              // 满多少元|还是满多少件
                        'discount_rule' => $d,                                      // 满足的那条规则
                        'goods_ids' => join(',', $goodsIds)                         // 这个活动包含的这次购买的商品
                    ];

                    // 累加促销活动总计优惠金额
                    $activity_discount_money = bcadd($activity_discount_money, $current_activity_discount_money, 2);

                    // 拼接参与的活动类型
                    $activity_type .= $discount['type'] . ',';
                    break;
                }
            } else if ($discount['type'] == 'free_shipping') {
                // 判断除外的地区
                $area_except = $rules['area_except'] ?? '';
                $city_except = $rules['city_except'] ?? '';
                $province_except = $rules['province_except'] ?? '';
                if ($user_address) {
                    if (
                        strpos($area_except, strval($user_address['area_id'])) !== false
                        || strpos($city_except, strval($user_address['city_id'])) !== false
                        || strpos($province_except, strval($user_address['province_id'])) !== false
                    ) {
                        // 收货地址在非包邮地区，则继续循环下个活动
                        continue;
                    }
                } else if ($area_except || $city_except || $province_except) {
                    // 没有选择收货地址，并且活动中包含地区限制,不计算活动
                    continue;
                }

                if (${$compareif} < $rules['full_num']) {
                    // 不满足条件，接着循环下个规则
                    continue;
                }

                // 记录活动信息
                $activity_discount_infos[] = [
                    'activity_id' => $discount['id'],                                   // 活动id
                    'activity_title' => $discount['title'],                             // 活动标题
                    'activity_type' => $discount['type'],                               // 活动类型
                    'activity_discount_money' => $current_discount_dispatch_amount,     // 活动减免的运费
                    'rule_type' => $rules['type'],                                      // 满多少元|还是满多少件
                    'discount_rule' => [
                        'full_num' => $rules['full_num']
                    ],                                                                  // 满足的那条规则
                    'goods_ids' => join(',', $goodsIds)                                 // 这个活动包含的这次购买的商品
                ];

                // 免运费的活动的id
                $free_shipping_goods_ids = array_merge($free_shipping_goods_ids, $goodsIds);

                // 拼接参与的活动类型
                $activity_type .= $discount['type'] . ',';
            }
        }

        // 多活动拼接去掉多余的 ,
        $activity_type = rtrim($activity_type, ',');

        // 重新计算运费
        if ($free_shipping_goods_ids) {
            // 存在免运费的商品
            $new_dispatch_amount = 0;
            foreach ($new_goods_list as $goods) {
                if (!in_array($goods['goods_id'], $free_shipping_goods_ids)) {
                    // 如果这个商品不在包邮活动中,计算运费
                    $bcres = bccomp($new_dispatch_amount, $goods['dispatch_amount'], 2);     // 比较两个运费
                    $new_dispatch_amount = ($bcres == 1 || $bcres == 0) ? $new_dispatch_amount : $goods['dispatch_amount'];      // 取用最大的
                }
            }

            if ($new_dispatch_amount < $dispatch_amount) {
                // 邮费省钱了
                $dispatch_discount_money = bcsub($dispatch_amount, $new_dispatch_amount, 2);     // 邮费实际节省金额
                // $activity_discount_money = bcadd($activity_discount_money, $dispatch_discount_money, 2);                // 邮费优惠，不计入活动总优惠金额中
                // $dispatch_amount = $new_dispatch_amount;        // 重新赋值运费，新计算的运费，不可能比 dispatch_amount 大（（搜这个找注释）ps: 这里还展示真实的运费，因为优惠总金额已经加上了优惠的运费）
            }
        }

        // 计算优惠券费用
        $user_coupons = null;
        $coupon_money = 0;
        if ($coupons_id) {
            $result = true;
            if (strpos($activity_type, 'seckill') !== false || strpos($activity_type, 'groupon') !== false) {
                // 拼团或者秒杀
                $result = self::checkAndException('活动商品不可使用优惠券');
            }

            if ($result && $order_type == 'score') {
                $result = self::checkAndException('积分商品不可使用优惠券');
            }

            if ($result) {
                // 查询传来的优惠券 id 是否可用
                $coupons = self::coupons($params, $goods_amount);

                $current_coupons = null;        // 当前所选优惠券
                foreach ($coupons as $key => $coupon) {
                    if ($coupon['user_coupons_id'] == $coupons_id) {
                        $current_coupons = $coupon;
                        break;
                    }
                }

                if ($current_coupons) {
                    $coupon_money = $current_coupons->amount;     // 金额在 coupons 表存着
                    $user_coupons = UserCoupons::where('id', $coupons_id)->find();        // 用户优惠券
                } else {
                    self::checkAndException('优惠券不可用');
                }
            }
        }

        if ($activity_discount_infos) {
            // 将每个商品对应的 activity_type 放入 new_goods_list
            foreach ($activity_discount_infos as $info) {
                $goodsIds = explode(',', $info['goods_ids']);

                foreach ($goodsIds as $goods_id) {
                    // 寻找商品id 等于 $goods_id 的所有商品，存在同一个商品，购买多个规格的情况
                    foreach ($new_goods_list as $g_k => $goods) {
                        if ($goods['goods_id'] == $goods_id) {
                            if (strpos($new_goods_list[$g_k]['activity_type'], $info['activity_type']) === false) {
                                $new_goods_list[$g_k]['activity_type'] .= $info['activity_type'] . ',';
                            }
                        }
                    }
                }
            }

            // 去除多余的 ,
            foreach ($new_goods_list as $key => $goods) {
                $new_goods_list[$key]['activity_type'] = rtrim($new_goods_list[$key]['activity_type'], ',');
            }
        }

        return [
            $new_goods_list,
            $activity_discount_infos,
            $activity_discount_money,
            $dispatch_discount_money,
            $free_shipping_goods_ids,
            $activity_type,
            $dispatch_amount,
            $user_coupons,
            $coupon_money
        ];
    }


    /**
     * 计算订单费用
     *
     * @param array $new_goods_list
     * @param float $goods_amount
     * @param float $origin_dispatch_amount 原始运费
     * @param float $dispatch_amount
     * @param int $score_amount
     * @param float $activity_discount_money
     * @param float $coupon_money
     * @return array
     */
    public static function preCalcOrder(
        $new_goods_list,
        $goods_amount,
        $origin_dispatch_amount,
        $dispatch_amount,
        $cook_service_amount,
        $activity_discount_infos,
        $activity_discount_money,
        $dispatch_discount_money,
        $cook_service_discount_money,
        $free_shipping_goods_ids,
        $coupon_money
    ) {
        // （搜这个找注释）加上运费，dispatch_amount 没有重新计算，和 origin_dispatch_amount 相同
        $total_amount = bcadd($goods_amount, $dispatch_amount, 2);
        $total_amount = bcadd($total_amount, $cook_service_amount, 2);// 厨师上门费用
        $coupon_fee = $coupon_money;
        $discount_fee = bcadd($coupon_money, $activity_discount_money, 2);
        $discount_fee = bcadd($discount_fee, $dispatch_discount_money, 2);
        $discount_fee = bcadd($discount_fee, $cook_service_discount_money, 2);// 厨师上门费用优惠
        $total_fee = bcsub($total_amount, $discount_fee, 2);
        $total_fee = $total_fee < 0 ? 0 : $total_fee;

        $new_goods_list = self::calcDiscountFee(
            $new_goods_list,
            $goods_amount,
            $discount_fee,
            $activity_discount_infos,
            $activity_discount_money,
            $dispatch_discount_money,
            $free_shipping_goods_ids
        );

        return [
            $new_goods_list,
            $total_amount,
            $discount_fee,
            $total_fee,
            $coupon_fee,
        ];
    }

    /**
     * 计算发票费用
     *
     * @param float $total_fee  实际支付价
     * @param float $goods_amount 商品价
     * @return float
     */
    public static function preCalcInvoiceAmount($total_fee, $goods_amount)
    {
        $config = \addons\shopro\model\Config::where('name', 'order')->find();
        $config = isset($config) ? json_decode($config['value'], true) : [];

        if (isset($config['invoice']) && $config['invoice']['enable']) {
            $price_type = $config['invoice']['price_type'];
            if ($price_type === 'goods_price') {
                return $goods_amount;
            } elseif ($price_type === 'pay_price') {
                return $total_fee;
            }
        }
        return 0;
    }

    /**
     * 处理返回结果
     *
     * @param float $goods_original_amount
     * @param float $goods_amount
     * @param float $dispatch_amount
     * @param float $total_amount
     * @param float $total_fee
     * @param float $discount_fee
     * @param float $coupon_fee
     * @param float $activity_discount_money
     * @param string $activity_type
     * @param int $score_amount
     * @param array $new_goods_list
     * @param array $need_address
     * @param array $activity_discount_infos
     * @param array $user_coupons
     * @param array $user_address
     * @param array $invoice_amount
     * @param array $is_taocan
     * @return array
     */
    public static function preReturnParams(
        $goods_original_amount,
        $goods_amount,
        $origin_dispatch_amount,
        $dispatch_amount,
        $cook_service_amount,
        $total_amount,
        $total_fee,
        $discount_fee,
        $coupon_fee,
        $activity_discount_money,
        $dispatch_discount_money,
        $cook_service_discount_money,
        $activity_type,
        $score_amount,
        $new_goods_list,
        $need_address,
        $activity_discount_infos,
        $user_coupons,
        $user_address,
        $invoice_amount,
        $is_taocan
    ) {
        // 需要处理小数点的数据
        $result = compact(
            "goods_original_amount",
            "goods_amount",
            "origin_dispatch_amount",
            "dispatch_amount",
            "cook_service_amount",
            "total_amount",
            "total_fee",
            "discount_fee",
            "coupon_fee",
            "activity_discount_money",
            "dispatch_discount_money",
            "cook_service_discount_money",
            "invoice_amount"
        );

        // 处理小数点保留两位小数
        foreach ($result as $key => $amount) {
            $result[$key] = number_format($amount, 2, '.', '');
        }

        // 合并不需要处理小数点的
        $result = array_merge($result, compact(
            "activity_type",
            "score_amount",
            "new_goods_list",
            "need_address",
            "activity_discount_infos",
            "is_taocan"
        ));

        // 如果是下单，合并 优惠券， 收货地址
        if (self::$calc_type == 'create') {
            $result = array_merge($result, compact(
                "user_coupons",
                "user_address"
            ));
        } else {
            $result = array_merge($result, ['msg' => self::$msg]);
        }

        return $result;
    }



    /**
     * 计算每个商品在优惠券，活动中应该分配到的优惠
     *
     * @param array $new_goods_list
     * @param float $goods_amount
     * @param float $discount_fee
     * @param array $activity_discount_infos
     * @param float $activity_discount_money
     * @param float $dispatch_discount_money
     * @param array $free_shipping_goods_ids
     * @return array
     */
    private static function calcDiscountFee(
        $new_goods_list,
        $goods_amount,
        $discount_fee,
        $activity_discount_infos,
        $activity_discount_money,
        $dispatch_discount_money,
        $free_shipping_goods_ids = []
    ) {
        // 如果邮费有优惠，分配邮费优惠的金额
        if (floatval($dispatch_discount_money)) {
            // 邮费有优惠，平分邮费的优惠
            $free_dispatch_total_money = 0; // 邮费总金额
            foreach ($new_goods_list as $key => $buyinfo) {
                if (in_array($buyinfo['goods_id'], $free_shipping_goods_ids)) {
                    $free_dispatch_total_money = bcadd($free_dispatch_total_money, $buyinfo['dispatch_amount'], 2);
                }
            }

            // 计算邮费上每个包邮商品分到的优惠
            foreach ($new_goods_list as $key => $buyinfo) {
                if (!in_array($buyinfo['goods_id'], $free_shipping_goods_ids)) {
                    // 不是包邮的商品，跳过
                    continue;
                }

                $scale = 0;                             // 按照商品价格和总价计算每个 item 的比例
                if (floatval($free_dispatch_total_money)) {          // 字符串 0.00 是 true, 这里转下类型在判断
                    $scale = bcdiv($buyinfo['dispatch_amount'], $free_dispatch_total_money, 6);
                }

                // 每个商品分配到的折扣
                $current_dispatch_discount_fee = round(bcmul($dispatch_discount_money, $scale, 3), 2);
                $new_goods_list[$key]['discount_fee'] = bcadd($new_goods_list[$key]['discount_fee'], $current_dispatch_discount_fee, 2);
            }
        }

        if (floatval($activity_discount_money)) {
            foreach ($activity_discount_infos as $key => $info) {
                if ($info['activity_type'] == 'free_shipping') {
                    // 上面已经单独处理过满包邮优惠
                    continue;
                }
                $current_discount_goods_ids = explode(',', $info['goods_ids']);
                $current_total_goods_amount = 0; // 当前活动的商品总金额
                foreach ($new_goods_list as $key => $buyinfo) {
                    if (in_array($buyinfo['goods_id'], $current_discount_goods_ids)) {
                        $current_total_goods_amount = bcadd($current_total_goods_amount, $buyinfo['goods_amount'], 2);
                    }
                }

                // 计算当前活动商品，每个商品应该分配到的优惠金额
                foreach ($new_goods_list as $key => $buyinfo) {
                    if (!in_array($buyinfo['goods_id'], $current_discount_goods_ids)) {
                        // 不是当前活动的商品，跳过
                        continue;
                    }

                    $scale = 0;                             // 按照商品价格和总价计算每个 item 的比例
                    if (floatval($current_total_goods_amount)) {          // 字符串 0.00 是 true, 这里转下类型在判断
                        $scale = bcdiv($buyinfo['goods_amount'], $current_total_goods_amount, 6);
                    }

                    // 每个商品分配到的折扣
                    $current_activity_discount_fee = round(bcmul($info['activity_discount_money'], $scale, 3), 2);
                    $new_goods_list[$key]['discount_fee'] = bcadd($new_goods_list[$key]['discount_fee'], $current_activity_discount_fee, 2);
                }
            }
        }

        // 剩余要加权平均分配的优惠
        $last_discount_money = bcsub($discount_fee, bcadd($dispatch_discount_money, $activity_discount_money, 2), 2);
        // 计算每个商品分配到的优惠券的优惠金额，顺便计算出来 pay_price 的金额
        foreach ($new_goods_list as $key => $buyinfo) {
            $scale = 0;                             // 按照商品价格和总价计算每个 item 的比例
            if (floatval($goods_amount)) {          // 字符串 0.00 是 true, 这里转下类型在判断
                $scale = bcdiv($buyinfo['goods_amount'], $goods_amount, 6);
            }

            // 每个商品分配到的折扣
            $current_discount_fee = round(bcmul($last_discount_money, $scale, 3), 2);
            $new_goods_list[$key]['discount_fee'] = bcadd($new_goods_list[$key]['discount_fee'], $current_discount_fee, 2);
            // 每个商品除了运费之后分配的支付金额
            $new_goods_list[$key]['pay_price'] = bcsub($buyinfo['goods_amount'], $new_goods_list[$key]['discount_fee'], 2);
        }

        return $new_goods_list;
    }
}
