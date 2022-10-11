<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use traits\model\SoftDelete;

/**
 * 优惠券模型
 */
class Coupons extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_coupons';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime'];


    // 追加属性
    protected $append = [

    ];
    const COUPONS_CENTER = 0; // 领券中心
    const COUPONS_CAN_USE = 1; // 可使用
    const COUPONS_CANNOT_USED = 2; // 暂不可用
    const COUPONS_EXPIRED = 3; // 已失效（包含已使用，和已过期的）

    public static function getCoupon($id)
    {
        $coupon = self::get($id);
        $user = User::info();
        if (!$coupon) {
            new Exception('未找到优惠券');
        }
        
        if ($coupon['gettimestart'] > time() || $coupon['gettimeend'] < time()) {
            new Exception('优惠券领取已结束');
        }

        $getList = UserCoupons::all([
            'user_id' => $user->id,
            'coupons_id' => $id
        ]);
        if (count($getList) >= $coupon->limit) {
            new Exception('您已经领取过');
        }

        if ($coupon->stock <= 0) {
            new Exception('优惠券已经被领完了');
        }
        $coupon->stock -= 1;
        $coupon->save();

        $result = UserCoupons::create([
            'user_id' => $user->id,
            'coupons_id' => $id,
        ]);
        return $result;
    }

    public static function getCouponsListByIds($ids)
    {
        $user = User::info();

        $couponsIdsArray = explode(',', $ids);
        $where = [
            'id' => ['in', $couponsIdsArray],
            'gettimestart' => ['elt', time()],
            'gettimeend' => ['egt', time()]
        ];

        $coupons = new self;
        if ($user) {
            // 关联用户状态
            $coupons = self::with(['userCoupons' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]);
        }
        $coupons = $coupons->where($where)->select();

        // 判断优惠券，当前用户领取状态
        foreach ($coupons as &$coupon) {
            if ($user && $coupon->limit <= count($coupon->user_coupons)) {
                $coupon->status_code = 'cannot_get';
                $coupon->status_name = '已领取';
            } else {
                $coupon->status_code = 'can_get';
                $coupon->status_name = '可以领取';
            }
        }

        return $coupons;
    }

    public static function getCouponsDetail($id, $user_coupons_id = 0)
    {
        $user = User::info();
        $coupon = self::get($id);

        // 查询并返回用户的优惠券状态
        if ($coupon && $user_coupons_id && $user) {            
            $userCoupons = UserCoupons::where('id', $user_coupons_id)
                    ->where('coupons_id', $coupon->id)
                    ->where('user_id', $user->id)->find();
            
            if ($userCoupons) {
                $coupon->user_coupons_id = $userCoupons->id;

                if ($userCoupons->usetime) {
                    $coupon->status_code = 'used';
                    $coupon->status_name = '已使用';
                } else {
                    if ($coupon->usetimestart <= time() && $coupon->usetimeend >= time()) {
                        $coupon->status_code = 'can_use';
                        $coupon->status_name = '可使用';
                    } else if ($coupon->usetimeend <= time()) {
                        $coupon->status_code = 'expired';
                        $coupon->status_name = '已过期';
                    } else {
                        // 未到使用日期
                        $coupon->status_code = 'cannot_use';
                        $coupon->status_name = '暂不可用';
                    }
                }
            }
        }

        return $coupon;
    }

    public static function getGoodsByCoupons($id)
    {
        $goodsIds = self::where('id', $id)->value('goods_ids');
        return Goods::getGoodsListByIds($goodsIds);


    }

    public static function getCouponsList($type)
    {
        $user = User::info();
        $couponsList = [];
        switch ($type) {
            case self::COUPONS_CENTER:
                $couponsList = self::with(['userCoupons' => function($query) use ($user) {
                    $query->where('user_id', $user->id);
                }])->where([
                    'gettimestart' => ['elt', time()],
                    'gettimeend' => ['egt', time()]
                ])->order('createtime', 'desc')->select();

                foreach($couponsList as &$coupon) {
                    if ($coupon->limit > count($coupon->user_coupons)) {
                        $coupon->status_code = 'can_get';
                        $coupon->status_name = '可以领取';
                    } else {
                        $coupon->status_code = 'cannot_get';
                        $coupon->status_name = '已领取';
                    }
                }
                break;
            case self::COUPONS_CAN_USE:
                $userCoupons = UserCoupons::where(['user_id' => $user->id,'usetime' => null])->order('createtime', 'desc')->select();
                foreach ($userCoupons as $u) {
                    $coupon = self::get($u->coupons_id);
                    if ($coupon && $coupon->usetimestart <= time() && $coupon->usetimeend >= time()) {
                        $coupon->user_coupons_id = $u->id;
                        $coupon->status_code = 'can_use';
                        $coupon->status_name = '可使用';
                        $couponsList[] = $coupon;
                    }
                }
                
                break;
            case self::COUPONS_CANNOT_USED:
                $userCoupons = UserCoupons::where(['user_id' => $user->id, 'usetime' => null])->order('createtime', 'desc')->select();
                foreach ($userCoupons as $u) {
                    $coupon = self::get($u->coupons_id);
                    if ($coupon && $coupon->usetimestart > time()) {
                        // 还没到可使用时间
                        $coupon->user_coupons_id = $u->id;
                        $coupon->status_code = 'cannot_use';
                        $coupon->status_name = '暂不可用';
                        $couponsList[] = $coupon;
                    }
                }
                break;
            case self::COUPONS_EXPIRED:
                $userCoupons = UserCoupons::where('user_id', $user->id)->order('createtime', 'desc')->select();
                foreach ($userCoupons as $u) {
                    $coupon = self::get($u->coupons_id);
                    if ($coupon && !is_null($u->usetime)) {
                        $coupon->user_coupons_id = $u->id;
                        $coupon->status_code = 'used';
                        $coupon->status_name = '已使用';
                        $couponsList[] = $coupon;
                    }

                    if ($coupon && is_null($u->usetime) && $coupon->usetimeend <= time()) {
                        $coupon->user_coupons_id = $u->id;
                        $coupon->status_code = 'expired';
                        $coupon->status_name = '已过期';
                        $couponsList[] = $coupon;
                    }
                }
                break;
        }

        return $couponsList;

    }

    public function getUsetimeAttr($value, $data)
    {
        $usetimeArray = explode(' - ', $value);
        $usetime['start'] = strtotime($usetimeArray[0]);
        $usetime['end'] = strtotime($usetimeArray[1]);
        return $usetime;
    }

    public function getGettimeAttr($value, $data)
    {
        $gettimeArray = explode(' - ', $value);
        $gettime['start'] = strtotime($gettimeArray[0]);
        $gettime['end'] = strtotime($gettimeArray[1]);
        return $gettime;
    }

    //定义关联方法
    public function userCoupons(){
        //hasMany('租客表名','租客表外键','宿舍主键',['模型别名定义']);
        return $this->hasMany('userCoupons','coupons_id','id');
    }



}
