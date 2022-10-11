<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\exception\Exception;
use addons\shopro\model\Coupons;
use addons\shopro\model\Goods;
use addons\shopro\model\User;
use addons\shopro\model\GoodsComment;
use addons\shopro\model\Order;
use addons\shopro\model\OrderAction;
use addons\shopro\model\OrderItem;
use addons\shopro\model\Store;
use addons\shopro\model\Verify;
use app\admin\model\shopro\order\RefundLog;
use app\admin\model\shopro\Systemconfig;
use think\Db;
use think\Env;

trait OrderOper
{
    use OrderOperSendGet, OrderOperCreate;

    // 获取订单号
    public static function getSn($user_id)
    {
        $rand = $user_id < 9999 ? mt_rand(100000, 99999999) : mt_rand(100, 99999);
        $order_sn = date('Yhis') . $rand;

        $id = str_pad($user_id, (24 - strlen($order_sn)), '0', STR_PAD_BOTH);

        return $order_sn . $id;
    }


    // 计算订单
    public static function pre($params, $calc_type = 'pre')
    {
        self::$calc_type = $calc_type;

        // 检测必要系统环境
        checkEnv(['bcmath', 'queue']);

        // 获取请求参数 "order_type", "groupon_id", "buy_type", "is_cook"
        extract(self::preParams($params));

        // 检测并重新组装要购买的商品列表
        list(
            $new_goods_list,            // 组合好的新的商品列表
            $activity_type,             // 订单参与的活动类型，后面还需计算一次
            $activity_discounts,        // 订单可能参与的所有活动
            $need_address,              // 是否需要收货地址
            $user_address               // 用户收货地址
        ) = self::preCheck($params);

        // 计算订单商品价格，所需积分，运费
        list(
            $new_goods_list,            // 新的商品列表
            $goods_original_amount,     // 商品原始总价
            $goods_amount,              // 商品现在总价
            $dispatch_amount,           // 订单运费（所有商品种最高的，后面还需根据活动再次计算）
            $score_amount,              // 订单所需支付积分
        ) = self::preCalcAmount($params, $new_goods_list, $user_address);

        // 记录原始运费
        $origin_dispatch_amount = $dispatch_amount;

        // 计算订单优惠折扣，优惠券,邮费
        list(
            $new_goods_list,                // 重新赋值活动， 商品上增加了 activity_type
            $activity_discount_infos,       // 每个活动包含的优惠信息
            $activity_discount_money,       // 促销活动优惠总金额
            $dispatch_discount_money,       // 邮费总优惠金额
            $free_shipping_goods_ids,       // 包邮的商品的ids
            $activity_type,                 // 全部参与的活动类型
            $dispatch_amount,               // 重新计算的运费
            $user_coupons,                  // 使用的优惠券信息
            $coupon_money                   // 优惠券优惠金额
        ) = self::preCalcDiscount(
            $params,
            $new_goods_list,
            $activity_discounts,
            $activity_type,
            $goods_amount,
            $dispatch_amount,
            $user_address
        );

        // 判断商品是否是套餐
        $is_taocan = 0;//套餐
        $goods_list = $params['goods_list'];
        $taocan_category = Env::get('vip.taocan_category');
        foreach ($goods_list as $k => $v){
            $goods = Goods::find($v['goods_id']);
            $category_ids = explode(',', $goods['category_ids']);
            if(in_array($taocan_category, $category_ids)){
                $is_taocan = 1;
                break;
            }
        }

        $is_cook = $params['is_cook'] ?? 2;        // 厨师上门 1是 2否
        // 厨师上门服务费
        if($is_cook == 1){
            $systemConfig = Systemconfig::where('name', '=', 'cook_service_fee')->find();
            $cook_service_amount = $systemConfig['value'];
            $cook_service_discount_money = 0;
            if($is_taocan == 1){
                $cook_service_discount_money = $cook_service_amount;
            }
        } else {
            $cook_service_amount = 0;
            $cook_service_discount_money = 0;
        }

        // 计算订单总金额，需支付金额
        list(
            $new_goods_list,
            $total_amount,
            $discount_fee,
            $total_fee,
            $coupon_fee,
        ) = self::preCalcOrder(
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
        );

        // 获取发票金额
        $invoice_amount = self::preCalcInvoiceAmount($total_fee, $goods_amount);

        // 处理返回结果
        return self::preReturnParams(
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
        );
    }


    // 获取可用优惠券列表
    public static function coupons($params, $goods_amount = 0)
    {
        $user = User::info();
        extract($params);
        $order_type = $order_type ?? 'goods';
        $groupon_id = $groupon_id ?? 0;        // 拼团的 团 id
        $buy_type = $buy_type ?? 'alone';       // 拼团的 购买方式: alone=单独购买,groupon=开团

        // 商品总金额
        if (!$goods_amount) {
            // 下单的时候把计算好的 goods_amount 传进来了，接口直接获取可用列表的时候，需要计算
            foreach ($goods_list as $k => $goods) {
                $goods_amount += ($goods['goods_price'] * $goods['goods_num']);
            }
        }

        $coupons = Coupons::getCouponsList(Coupons::COUPONS_CAN_USE);

        $new_coupons = [];
        // 过滤，如果有一个产品不适用，则优惠券不允许使用,不显示
        foreach ($coupons as $key => $coupon) {
            if ($coupon['goods_ids'] === '0') {
                // 所有商品可用
                $can_use = true;
            } else {
                $goods_ids = explode(',', $coupon['goods_ids']);

                $can_use = true;
                foreach ($goods_list as $k => $goods) {
                    if (!in_array($goods['goods_id'], $goods_ids)) {
                        $can_use = false;
                        break;
                    }
                }
            }

            // 商品可用 并且 商品金额满足
            if ($can_use && $coupon->enough <= $goods_amount) {
                $new_coupons[] = $coupon;
            }
        }

        $new_coupons = array_values($new_coupons);

        return $new_coupons;
    }


    public static function createOrder($params)
    {
        $user = User::info();

        // 入参
        extract($params);
        $remark = $remark ?? null;
        $order_type = $order_type ?? 'goods';
        $groupon_id = $groupon_id ?? 0;        // 拼团的 团 id
        $buy_type = $buy_type ?? 'alone';       // 拼团的 购买方式: alone=单独购买,groupon=开团
        $invoice = $invoice ?? null;      // 发票申请
        $is_cook = $is_cook ?? 2;      // 是否需要厨师 1需要 2不需要

        // 订单计算数据
        extract(self::pre($params, "create"));

        $order = Db::transaction(function () use (
            $user,
            $order_type,
            $groupon_id,
            $buy_type,
            $goods_original_amount,
            $goods_amount,
            $dispatch_amount,
            $cook_service_amount,
            $total_amount,
            $score_amount,
            $total_fee,
            $discount_fee,
            $coupon_fee,
            $activity_discount_money,
            $dispatch_discount_money,
            $cook_service_discount_money,
            $activity_discount_infos,
            $new_goods_list,
            $activity_type,
            $user_coupons,
            $user_address,
            $remark,
            $from,
            $invoice,
            $invoice_amount,
            $is_cook
        ) {
            // 订单创建前
            $data = [
                'user' => $user,
                'order_type' => $order_type,
                'groupon_id' => $groupon_id,
                'buy_type' => $buy_type,
                'is_cook' => $is_cook,
                'goods_original_amount' => $goods_original_amount,
                'goods_amount' => $goods_amount,
                'dispatch_amount' => $dispatch_amount,
                'cook_service_amount' => $cook_service_amount,
                'total_amount' => $total_amount,
                'score_amount' => $score_amount,
                'total_fee' => $total_fee,
                'discount_fee' => $discount_fee,
                'coupon_fee' => $coupon_fee,
                'activity_discount_money' => $activity_discount_money,
                'dispatch_discount_money' => $dispatch_discount_money,
                'cook_service_discount_money' => $cook_service_discount_money,
                'activity_discount_infos' => $activity_discount_infos,
                'new_goods_list' => $new_goods_list,
                'activity_type' => $activity_type,
                'user_coupons' => $user_coupons,
                'user_address' => $user_address,
                'remark' => $remark,
                'from' => $from
            ];
            // 如果是活动，这里面减掉 redis 库存
            \think\Hook::listen('order_create_before', $data);
            // 团信息, 如果是参与旧团拼团才会不为 null，（开新团放到支付成功）
            $groupon = cache('grouponinfo-' . $user->id);
            $groupon = $groupon ? json_decode($groupon, true) : null;
            // 立即清除缓存
            cache('grouponinfo-' . $user->id, NULL);

            $orderData = [];
            $orderData['order_sn'] = self::getSn($user->id);
            $orderData['user_id'] = $user->id;
            $orderData['is_cook'] = $is_cook;
            $orderData['type'] = $order_type;
            $orderData['activity_type'] = $activity_type;
            $orderData['goods_amount'] = $goods_amount;
            $orderData['dispatch_amount'] = $dispatch_amount;
            $orderData['cook_service_amount'] = $cook_service_amount;
            $orderData['total_amount'] = $total_amount;
            $orderData['score_amount'] = $score_amount;
            $orderData['total_fee'] = $total_fee;
            $orderData['discount_fee'] = $discount_fee;
            $orderData['score_fee'] = $score_amount;          // 记录score 支付数
            $orderData['coupon_fee'] = $coupon_fee;
            $orderData['activity_discount_money'] = $activity_discount_money;
            $orderData['dispatch_discount_money'] = $dispatch_discount_money;
            $orderData['cook_service_discount_money'] = $cook_service_discount_money;
            $orderData['goods_original_amount'] = $goods_original_amount;

            if ($user_address) {
                $orderData['phone'] = $user_address->phone;
                $orderData['consignee'] = $user_address->consignee;
                $orderData['province_name'] = $user_address->province_name;
                $orderData['city_name'] = $user_address->city_name;
                $orderData['area_name'] = $user_address->area_name;
                $orderData['address'] = $user_address->address;
                $orderData['province_id'] = $user_address->province_id;
                $orderData['city_id'] = $user_address->city_id;
                $orderData['area_id'] = $user_address->area_id;
            }

            // 处理发票申请
            if($invoice_amount > 0) {
                if(!empty($invoice) && $invoice['amount'] == $invoice_amount) {
                    $orderData['invoice_status'] = 1;   // 已申请
                }else {
                    $orderData['invoice_status'] = 0;   // 未申请
                }
            }else {
                $orderData['invoice_status'] = -1;  // 不可开具发票

            }
            $orderData['status'] = 0;
            $orderData['remark'] = $remark;
            $orderData['coupons_id'] = $user_coupons ? $user_coupons->id : 0;
            $orderData['platform'] = request()->header('platform');

            $ext = $activity_discount_infos ? ['activity_discount_infos' => $activity_discount_infos] : [];       // 促销活动信息
            $orderData['ext'] = json_encode($ext);
            $order = new Order();
            $order->allowField(true)->save($orderData);

            // 将优惠券使用掉
            if ($user_coupons) {
                $user_coupons->use_order_id = $order->id;
                $user_coupons->usetime = time();
                $user_coupons->save();
            }

            // 如果需要支付积分,扣除积分
            if ($score_amount) {
                // $user 为 Common\Auth 对象
                User::score(-$score_amount, $user->id, 'score_pay', $order->id, '', [
                    'order_id' => $order->id,
                    'order_sn' => $order->order_sn,
                ]);
            }

            // 添加发票数据
            if($order->invoice_status == 1) {
                \addons\shopro\model\OrderInvoice::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'amount' => $invoice['amount'],
                    'type' => $invoice['type'],
                    'header_name' => $invoice['header_name'],
                    'tax_no' => $invoice['tax_no'],
                    'mobile' => $invoice['mobile'],
                ]);
            }

            // 添加 订单 item
            foreach ($new_goods_list as $key => $buyinfo) {
                $detail = $buyinfo['detail'];
                $current_sku_price = $detail['current_sku_price'];

                $orderItem = new OrderItem();

                $orderItem->user_id = $user->id;
                $orderItem->order_id = $order->id;
                $orderItem->goods_id = $buyinfo['goods_id'];
                $orderItem->goods_type = $detail['type'];
                $orderItem->goods_sku_price_id = $buyinfo['sku_price_id'];

                $item_activity_type = (isset($current_sku_price['activity_type']) && $current_sku_price['activity_type']) ? $current_sku_price['activity_type'] : '';
                $item_activity_type .= $buyinfo['activity_type'] ? ',' . $buyinfo['activity_type'] : '';
                $item_activity_type = trim($item_activity_type, ',');

                $orderItem->activity_id = $current_sku_price['activity_id'] ?? 0;     // 商品当前的活动类型
                $orderItem->activity_type = $item_activity_type ?: null;     // 商品当前的活动类型
                // 当前商品规格对应的 活动下对应商品规格的 id
                $orderItem->item_goods_sku_price_id = isset($current_sku_price['item_goods_sku_price']) ?
                    $current_sku_price['item_goods_sku_price']['id'] : 0;
                $orderItem->goods_sku_text = $current_sku_price['goods_sku_text'];
                $orderItem->goods_title = $detail->title;
                $orderItem->goods_image = empty($current_sku_price['image']) ? $detail->image : $current_sku_price['image'];
                $orderItem->goods_original_price = $detail->original_price;
                $orderItem->discount_fee = $buyinfo['discount_fee'];        // 平均计算单件商品所享受的折扣
                $orderItem->pay_price = $buyinfo['pay_price'];        // 平均计算单件商品不算运费，算折扣时候的金额
                $orderItem->goods_price = $detail->current_sku_price->price;
                $orderItem->goods_num = $buyinfo['goods_num'] ?? 1;
                $orderItem->goods_weight = $detail->current_sku_price->weight;
                $orderItem->dispatch_status = 0;
                $orderItem->dispatch_fee = $buyinfo['dispatch_amount'];
                $orderItem->dispatch_type = $buyinfo['dispatch_type'];
                $orderItem->dispatch_id = $buyinfo['dispatch_id'] ? $buyinfo['dispatch_id'] : 0;
                $orderItem->store_id = $buyinfo['store_id'] ? $buyinfo['store_id'] : 0;
                $orderItem->aftersale_status = 0;
                $orderItem->comment_status = 0;
                $orderItem->refund_status = 0;

                $ext = [];
                if (isset($buyinfo['dispatch_date'])) {
                    $ext['dispatch_date'] = $buyinfo['dispatch_date'];
                }
                if (isset($buyinfo['dispatch_phone'])) {
                    $ext['dispatch_phone'] = $buyinfo['dispatch_phone'];
                }
                $orderItem->ext = json_encode($ext);
                $orderItem->save();
            }

            // 订单创建后
            $data = [
                'user' => $user,
                'order' => $order,
                'from' => $from,
                'groupon' => $groupon,
                'buy_type' => $buy_type,
                'activity_type' => $activity_type,
                'new_goods_list' => $new_goods_list
            ];
            \think\Hook::listen('order_create_after', $data);

            // 重新获取订单
            $order = self::where('id', $order['id'])->find();

            return $order;
        });

        return $order;
    }


    // 订单列表
    public static function getList($params)
    {
        $user = User::info();
        extract($params);

        $order = (new self())->where('user_id', $user->id)->with('item');

        switch ($type) {
            case 'all':
                $order = $order;
                break;
            case 'nopay':
                $order = $order->nopay();
                break;
            case 'nosend':
                $order = $order->payed()->nosend();
                break;
            case 'noget':
                $order = $order->payed()->noget();
                break;
            case 'nocomment':
                $order = $order->payed()->nocomment();
                break;
            case 'aftersale':
                $order = $order->payed()->aftersale();      // 个人中心售后单不在走这里，而是直接走的售后单列表
                break;
        }

        $orders = $order->order('id', 'desc')->paginate(10);

        // 处理未支付订单 item status_code
        $orders = $orders->toArray();
        if ($orders['data']) {
            $data = $orders['data'];
            foreach ($data as $key => $od) {
                $data[$key] = self::setOrderItemStatusByOrder($od);
            }

            $orders['data'] = $data;
        }

        return $orders;
    }


    // 订单详情
    public static function detail($params)
    {
        $user = User::info();
        extract($params);

        $order = (new self())->with('item')->where('user_id', $user->id);

        if (isset($order_sn)) {
            $order = $order->where('order_sn', $order_sn);
        }
        if (isset($id)) {
            $order = $order->where('id', $id);
        }

        $order = $order->find();

        if (!$order) {
            new Exception('订单不存在');
        }

        // 处理未支付订单 item status_code
        $order = self::setOrderItemStatusByOrder($order);

        return $order;
    }


    // 订单商品详情
    public static function itemDetail($params)
    {
        $user = User::info();
        extract($params);
        $type = $type ?? 'default';

        $order = (new self())->with(['item' => function ($query) use ($order_item_id) {
            $query->where('id', $order_item_id);
        }])->where('user_id', $user->id);

        if (isset($order_sn)) {
            $order = $order->where('order_sn', $order_sn);
        }
        if (isset($id)) {
            $order = $order->where('id', $id);
        }

        $order = $order->find();

        if (!$order || !$order->item) {
            new Exception('订单不存在');
        }

        // 处理未支付订单 item status_code
        $order = self::setOrderItemStatusByOrder($order);
        $item = $order['item'][0];
        unset($order['item']);  // 移除订单表中的 item
        $item['order'] = $order;        // 订单信息

        if ($type == 'dispatch') {
            $store = null;
            $verifies = [];
            if (in_array($item['dispatch_type'], ['selfetch', 'store']) && $item['store_id']) {
                // 自提，商家配送

                if (
                    $item['dispatch_type'] == 'selfetch'
                    && $item['dispatch_status'] == OrderItem::DISPATCH_STATUS_SENDED
                    && $item['refund_status'] <= 0
                ) {
                    // 关联核销码
                    $verifies = Verify::where('order_id', $item['order_id'])
                        ->where('order_item_id', $item['id'])->select();
                }

                $store = Store::where('id', $item['store_id'])->find();
            }
            $item['store'] = $store;        // 门店信息
            $item['verify'] = $verifies;    // 核销券列表

            // $item['autosend']
            // 订单详情，自动发货内容 待补充
        }

        return $item;
    }




    // 取消订单
    public static function operCancel($params)
    {
        $user = User::info();
        extract($params);

        $order = self::with('item')->where('user_id', $user->id)->where('id', $id)->nopay()->find();

        if (!$order) {
            new Exception('订单不存在或已取消');
        }

        // 订单取消
        $order = (new self)->doCancel($order, $user);

        return $order;
    }


    public function doCancel($order, $user, $type = 'user')
    {
        $order = Db::transaction(function () use ($order, $user, $type) {
            $data = ['order' => $order];
            \think\Hook::listen('order_cancel_before', $data);

            $order->status = Order::STATUS_CANCEL;        // 取消订单
            $order->ext = json_encode($order->setExt($order, ['cancel_time' => time()]));      // 取消时间
            $order->save();

            OrderAction::operAdd($order, null, $user, $type, ($type == 'user' ? '用户' : '管理员') . '取消订单');

            // 订单取消，退回库存，退回优惠券积分，等
            $data = ['order' => $order];
            \think\Hook::listen('order_cancel_after', $data);

            return $order;
        });

        return $order;
    }


    private static function getItem($order, $order_item_id)
    {
        if (!$order) {
            new Exception('当前订单不存在');
        }

        $order_item = null;
        foreach ($order->item as $item) {
            if ($item->id == $order_item_id) {
                $order_item = $item;
                break;
            }
        }

        if (!$order_item) {
            new Exception('订单商品不需要操作');
        }

        return $order_item;
    }


    // 确认收货订单
    public static function operConfirm($params)
    {
        $user = User::info();
        extract($params);

        $order = Db::transaction(function () use ($id, $order_item_id, $user) {
            // 加锁查询订单，exist 里面的子查询不会加锁，但是该语句需要等待锁释放才能正常查询，所以下面的获取 item 已经是更改过状态之后的了
            $order = self::noget()->where('user_id', $user->id)->where('id', $id)->lock(true)->find();

            // 获取要操作的 订单 item
            $item = self::getItem($order, $order_item_id);
            if ($item->dispatch_status == OrderItem::DISPATCH_STATUS_NOSEND) {
                new Exception('当前订单未发货');
            }
            if ($item->dispatch_status == OrderItem::DISPATCH_STATUS_GETED) {
                new Exception('当前订单已收货');
            }

            (new self())->getedItem($order, $item, ['oper_type' => 'user']);
        });

        return $order;
    }



    // 删除订单
    public static function operDelete($params)
    {
        $user = User::info();
        extract($params);

        $order = self::canDelete()->where('user_id', $user->id)->where('id', $id)->find();

        if (!$order) {
            new Exception('订单不存在或不可删除');
        }

        $order = Db::transaction(function () use ($order, $user) {
            $order->delete();        // 删除订单

            OrderAction::operAdd($order, null, $user, 'user', '用户删除订单');

            return $order;
        });

        return $order;
    }



    // 评价
    public static function operComment($params)
    {
        $user = User::info();

        $order = Db::transaction(function () use ($user, $params) {
            extract($params);

            // 加锁读取订单，直到订单评价状态改变
            $order = self::with('item')->payed()->where('user_id', $user->id)->where('id', $id)->lock(true)->find();

            // 获取要操作的 订单 item
            $item = self::getItem($order, $order_item_id);
            if ($item->comment_status == 1) {
                new Exception('当前商品已评价');
            }

            // 订单评价前
            $data = ['order' => $order, 'item' => $item];
            \think\Hook::listen('order_comment_before', $data);     // 重新拿到更新过的订单

            $images = (isset($images) && $images) ? $images : null;

            // 获取用户评论配置
            $config = \addons\shopro\model\Config::where('name', 'order')->value('value');
            $config = json_decode($config, true);

            GoodsComment::create([
                'goods_id' => $item->goods_id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'user_id' => $user->id,
                'level' => $level,
                'content' => $content,
                'images' => $images ? implode(',', $images) : $images,
                'status' => !empty($config['user_reply']) ? 'show' : 'hidden'
            ]);

            $item->comment_status = OrderItem::COMMENT_STATUS_OK;        // 评价成功
            $item->save();

            OrderAction::operAdd($order, $item, $user, 'user', '用户评价成功');

            // 订单评价后
            $data = ['order' => $order, 'item' => $item];
            \think\Hook::listen('order_comment_after', $data);

            return $order;
        });

        return $order;
    }



    // 个人中心订单数量
    public static function statusNum()
    {
        $user = User::info();

        $status_num['nopay'] = self::where('user_id', $user->id)->nopay()->count();
        $status_num['nosend'] = self::where('user_id', $user->id)->payed()->nosend()->count();
        $status_num['noget'] = self::where('user_id', $user->id)->payed()->noget()->count();
        $status_num['nocomment'] = self::where('user_id', $user->id)->payed()->nocomment()->count();
        // $status_num['aftersale'] = self::where('user_id', $user->id)->payed()->aftersale()->count();
        $status_num['aftersale'] = \addons\shopro\model\OrderAftersale::where('user_id', $user->id)->count();

        return $status_num;
    }


    public function paymentProcess($order, $notify)
    {
        $order->status = Order::STATUS_PAYED;
        $order->paytime = time();
        $order->transaction_id = $notify['transaction_id'];
        $order->payment_json = $notify['payment_json'];
        $order->pay_type = $notify['pay_type'];
        $order->pay_fee = $notify['pay_fee'];
        $order->save();

        $user = User::where('id', $order->user_id)->find();
        OrderAction::operAdd($order, null, $user, 'user', '用户支付成功');

        // 支付成功后续使用异步队列处理
        \think\Queue::push('\addons\shopro\job\OrderPayed@payed', ['order' => $order, 'user' => $user], 'shopro-high');
        return $order;
    }


    // 开始退款
    public static function startRefund($order, $item, $refund_money, $user = null, $remark = '')
    {
        // 订单退款前
        $data = ['order' => $order, 'item' => $item];
        \think\Hook::listen('order_refund_before', $data);

        $item->refund_status = \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_OK;    // 同意退款
        $item->refund_fee = $refund_money;
        $item->save();

        \addons\shopro\model\OrderAction::operAdd($order, $item, $user, ($user ? 'admin' : 'system'), $remark . '，退款金额：' . $refund_money);

        \app\admin\model\shopro\order\Order::refund($order, $item, $refund_money, $remark);

        // 订单退款后
        $data = ['order' => $order, 'item' => $item];
        \think\Hook::listen('order_refund_after', $data);
    }


    // 退款
    public static function refund($order, $item, $refund_money, $remark = '')
    {
        // 生成退款单
        $refundLog = new RefundLog();
        $refundLog->order_sn = $order->order_sn;
        $refundLog->refund_sn = RefundLog::getSn($order->user_id);
        $refundLog->order_item_id = $item->id;
        $refundLog->pay_fee = $order->pay_fee;
        $refundLog->refund_fee = $refund_money;
        $refundLog->pay_type = $order->pay_type;
        $refundLog->save();

        if ($order->pay_type == 'wechat' || $order->pay_type == 'alipay') {
            // 微信|支付宝退款

            // 退款数据
            $order_data = [
                'out_trade_no' => $order->order_sn
            ];

            if ($order->pay_type == 'wechat') {
                $total_fee = $order->pay_fee * 100;
                $refund_fee = $refund_money * 100;

                $order_data = array_merge($order_data, [
                    'out_refund_no' => $refundLog->refund_sn,
                    'total_fee' => $total_fee,
                    'refund_fee' => $refund_fee,
                    'refund_desc' => $remark,
                ]);
            } else {
                $order_data = array_merge($order_data, [
                    'out_request_no' => $refundLog->refund_sn,
                    'refund_amount' => $refund_money,
                ]);
            }

            $notify_url = request()->domain() . '/addons/shopro/pay/notifyr/payment/' . $order->pay_type . '/platform/' . $order->platform;

            $pay = new \addons\shopro\library\PayService($order->pay_type, $order->platform, $notify_url);
            $result = $pay->refund($order_data);

            \think\Log::write('refund-result' . json_encode($result));


            if ($order->pay_type == 'wechat') {
                // 微信通知回调 pay->notifyr
                if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
                    return true;
                } else {
                    throw new \Exception($result['return_msg']);
                }
            } else {
                // 支付宝通知回调 pay->notifyx
                if ($result['code'] == "10000") {
                    return true;
                } else {
                    throw new \Exception($result['msg']);
                }
            }

            // {        // 微信返回结果
            //     "return_code":"SUCCESS",
            //     "return_msg":"OK",
            //     "appid":"wx39cd0799d4567dd0",
            //     "mch_id":"1481069012",
            //     "nonce_str":"huW9eIAb5BDPn0Ma",
            //     "sign":"250316740B263FE53F5DFF50AF5A8FA1",
            //     "result_code":"SUCCESS",
            //     "transaction_id":"4200000497202004072822298902",
            //     "out_trade_no":"202010300857029180027000",
            //     "out_refund_no":"1586241595",
            //     "refund_id":"50300603862020040700031444448",
            //     "refund_channel":[],
            //     "refund_fee":"1",
            //     "coupon_refund_fee":"0",
            //     "total_fee":"1",
            //     "cash_fee":"1",
            //     "coupon_refund_count":"0",
            //     "cash_refund_fee":"1
            // }

            // {        // 支付宝返回结果
            //     "code": "10000",
            //     "msg": "Success",
            //     "buyer_logon_id": "157***@163.com",
            //     "buyer_user_id": "2088902485164146",
            //     "fund_change": "Y",
            //     "gmt_refund_pay": "2020-08-15 16:11:45",
            //     "out_trade_no": "202002460317545607015300",
            //     "refund_fee": "0.01",
            //     "send_back_fee": "0.00",
            //     "trade_no": "2020081522001464141438570535"
            // }
        } else if ($order->pay_type == 'wallet') {
            // 余额退款
            if ($refund_money != 0) {
                \addons\shopro\model\User::money($refund_money, $order->user_id, 'wallet_refund', $order->id, '', [
                    'order_id' => $order->id,
                    'order_sn' => $order->order_sn,
                    'item_id' => $item->id,
                ]);
            }

            self::refundFinish($order, $item, $refundLog);

            return true;
        } else if ($order->pay_type == 'score') {
            // 积分退款，暂不支持积分退款
        }
    }

    public static function refundFinish($order, $item, $refundLog)
    {
        // 退款完成
        $refundLog->status = 1;
        $refundLog->save();

        // 退款完成
        $item->refund_status = \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_FINISH;    // 退款完成
        $item->save();
        \addons\shopro\model\OrderAction::operAdd($order, $item, null, 'admin', '退款成功');
    }



    public function setExt($order, $field, $origin = [])
    {
        $newExt = array_merge($origin, $field);

        $orderExt = $order['ext_arr'];

        return array_merge($orderExt, $newExt);
    }
}
