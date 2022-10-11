<?php

namespace addons\shopro\model;

use think\Db;
use think\Env;
use think\Model;

/**
 * 购物车模型
 */
class Cart extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_cart';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime'];
    //列表动态隐藏字段
//    protected static $listHidden = ['content', 'params', 'images', 'service_ids'];

    // 追加属性
    protected $append = [
    ];

    public static function info()
    {
        $user = User::info();

        // 被物理删除的商品直接删掉购物车，只删除自己的
        $cartData = self::whereNotExists(function ($query) {
            $goodsTableName = (new Goods())->getQuery()->getTable();
            $tableName = (new self())->getQuery()->getTable();
            $query = $query->table($goodsTableName)->where($goodsTableName . '.id=' . $tableName . '.goods_id');

            return $query;
        })->where([
            'user_id' => $user->id
        ])->delete();

        $cartData = self::with(['goods' => function ($query) {
            $query->removeOption('soft_delete');
        }, 'sku_price' => function ($query) {
                $query->removeOption('soft_delete');
            }
        ])->where([
            'user_id' => $user->id
        ]);

        // 关联是否是活动
        $cartTableName = (new self)->getQuery()->getTable();
        // 这里只查秒杀拼团的， 并且不限制活动是否开始或者结束
        $actSubSql = Activity::field('type as activity_type, id as activity_id, goods_ids')->where('type', 'in', ['groupon', 'seckill'])->buildSql();
        $cartData = $cartData->join([$actSubSql => 'act'], "find_in_set(" . $cartTableName . ".goods_id, goods_ids)", 'left')->group('id');

        // 关闭 sql mode 的 ONLY_FULL_GROUP_BY
        $oldModes = closeStrict(['ONLY_FULL_GROUP_BY']);

        $cartData = $cartData->select();
        
        // 恢复 sql mode
        recoverStrict($oldModes);

        $group_id = $user->group_id;// 1普通会员 2vip会员
        $vip_goods_id = Env::get('vip.goods_id');//vip的商品id
        $vip_goods_type = Env::get('vip.goods_type');// vip特价菜的分类
        $vip_goods_num = 0;//vip特价菜数量
        $is_vip_goods = 0;//是否购买过特价菜
        $is_vip = 0;//购物车中是否有vip会员

        // 查询今天是否购买过特价菜
        $map = [];
        $map['user_id'] = $user->id;
        $map['buy_date'] = date('Ymd');
        $old_buy_special = Db::name('shopro_special_log')->where($map)->find();
        if($old_buy_special){
            $is_vip_goods = 1;
        }

        foreach ($cartData as $key => &$cart) {
            $cart['cart_type'] = null;

            $cart['error_msg'] = '';

            if ($cart['activity_type'] != null) {
                $cart['cart_type'] = 'activity';
            }

            if (!is_null($cart['goods']['deletetime']) || $cart['goods']['status'] === 'down' || empty($cart['sku_price']) || !is_null($cart['sku_price']['deletetime'])) {
                $cart['cart_type'] = 'invalid';
            }

            if($cart['goods']['id'] == $vip_goods_id){
                $is_vip = 1;
                if($group_id == Env::get('vip.group_id')){
                    // 如果购买的是vip会员，判断当前是否为vip会员，不能重复购买
                    $cart['error_msg'] = '您已是VIP会员，不需要再购买VIP会员';
                    continue;
                }
                if ($cart['goods_num'] > 1) {
                    // 如果购买的是vip会员，判断是否只选了一个，不能重复购买
                    $cart['error_msg'] = 'VIP会员数量请设置为1';
                }
            }

            if(in_array($vip_goods_type,explode(',', $cart['goods']['category_ids']))){
                if($is_vip_goods == 1){
                    $cart['error_msg'] = '今天您已购买过特价菜，每天限购一份';
                } else {
                    // 购买了特价菜
                    $vip_goods_num += $cart['goods_num'];
                    if($vip_goods_num > 1){
                        $cart['error_msg'] = '特价菜每天限购一份';
                    }
                }
            }
        }

        // 如果不是会员，购物车也没有购买会员，不允许购买特价菜
        if(count($cartData) > 0){
            if($group_id != 2 && $vip_goods_num > 0 && $is_vip != 1){
                $cartData[0]['error_msg'] = '加入VIP会员才能购买特价菜';
            }
        }

        return $cartData;
    }

    public static function add($goodsList)
    {

        $user = User::info();

        foreach ($goodsList as $v) {
            $where = [
                'user_id' => $user->id,
                'goods_id' => $v['goods_id'],
                'sku_price_id' => $v['sku_price_id'],
                'deletetime' => null
            ];
            $cart = self::get($where);
            if ($cart) {
                $cart->goods_num += $v['goods_num'];
                $cart->save();
            }else{
                $cartData = [
                    'user_id' => $user->id,
                    'goods_id' => $v['goods_id'],
                    'goods_num' => $v['goods_num'],
                    'sku_price_id' => $v['sku_price_id']
                ];
                $cart = self::create($cartData);
            }

        }

        return $cart;


    }

    public static function edit($params)
    {
        extract($params);
        $user = User::info();
        $where['user_id'] = $user->id;
        switch ($act) {
            case 'delete':
                foreach ($cart_list as $v) {
                    $where['id'] = $v;
                    self::where($where)->delete();
                }
                break;
            case 'change':
                foreach ($cart_list as $v) {
                    $where['id'] = $v;
                    self::where($where)->update(['goods_num' => $value]);
                }
                break;
        }

        return true;


    }

    public function goods()
    {
        return $this->hasOne(Goods::class, 'id', 'goods_id');
    }

    public function skuPrice()
    {
        return $this->hasOne(GoodsSkuPrice::class, 'id', 'sku_price_id');
    }
    

}
