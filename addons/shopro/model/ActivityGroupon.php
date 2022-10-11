<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Model;

/**
 * 活动-拼团
 */
class ActivityGroupon extends Model
{
    // 表名,不含前缀
    protected $name = 'shopro_activity_groupon';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = [];

    // 追加属性
    protected $append = [
    ];


    // 获取正在进行中的团
    public static function getActivityGroupon($params)
    {
        $goods_id = $params['goods_id'] ?? 0;
        $activity_id = $params['activity_id'] ?? 0;

        $activityGroupons = self::with('leader')
                            ->where('goods_id', $goods_id)
                            ->where('activity_id', $activity_id)
                            ->where('status', 'ing')
                            ->limit(10)
                            ->select();

        return $activityGroupons;
    }


    // 团详情
    public static function getActivityGrouponDetail ($id) {
        $activityGroupon = self::with(['my', 'grouponLog', 'activity' => function ($query) {
            $query->removeOption('soft_delete')->with('activity_goods_sku_price');     // 关联团所属活动，并关联活动规格
        }])->where('id', $id)->find();

        if (!$activityGroupon) {
            new Exception('团未找到');
        }

        $detail = Goods::getGoodsDetail($activityGroupon['goods_id']);      // 正常团选规格时候要用
        $activityGroupon['goods'] = $detail;

        // 初始化拼团价为商品价，防止活动被物理删除
        $activityGroupon['goods']['groupon_price'] = $activityGroupon['goods']['price'];
        // 初始化活动状态为已结束
        $activityGroupon['activity_status'] = 'ended';

        // 处理当时的活动价格
        if ($activityGroupon['activity']) {
            $activity = $activityGroupon['activity'];
            $activityGroupon['activity_status'] = $activity['status_code'];

            $currentGoodsActivitySkuPrices = [];
            foreach ($activity['activity_goods_sku_price'] as $k => $skuPrice) {
                if ($skuPrice['status'] == 'up' && $skuPrice['goods_id'] == $activityGroupon['goods_id']) {
                    $currentGoodsActivitySkuPrices[] = $skuPrice;
                }
            }

            if ($currentGoodsActivitySkuPrices) {
                // 当时参加活动真实销量
                $activityGroupon['goods']['sales'] = array_sum(array_column($currentGoodsActivitySkuPrices, 'sales'));
                // 这个是活动最低价
                $activityGroupon['goods']['groupon_price'] = $activityGroupon['goods']['price'] = min(array_column($currentGoodsActivitySkuPrices, 'price'));
            }
        }

        return $activityGroupon;
    }



    /**
     * 获取我的拼团列表
     *
     * @param [type] $type
     * @return void
     */
    public static function getMyGroupon($type) {
        $user = User::info();

        $logs = \addons\shopro\model\ActivityGrouponLog::with(['groupon', 'order.firstItem', 'goods' => function ($query) {
            $query->removeOption('soft_delete');        // 商品查询包括被软删除的
        }]);

        if ($type != 'all') {
            $type = $type == 'finish' ? ['finish', 'finish-fictitious'] : [$type];
            $logs = $logs->whereExists(function ($query) use ($type) {
                $log_name = (new \addons\shopro\model\ActivityGrouponLog())->getQuery()->getTable();
                $groupon_name = (new \addons\shopro\model\ActivityGroupon())->getQuery()->getTable();
                $query->table($groupon_name)->where('id=' . $log_name . '.groupon_id')
                        ->where('status', 'in', $type);
            });
        }

        $logs = $logs->where('user_id', $user->id)
                ->order('id', 'desc')
                ->paginate(10)->toArray();

        // 将列表的显示价格，都查当时购买的价格，和当时对应的活动的 真实销量
        if ($grouponLogs = $logs['data']) {
            // 拿到所有活动 ids
            $activity_ids = array_column($grouponLogs, 'activity_id');

            // 一次获取所有活动，包括被软删除的活动，并关联规格
            $activities = \addons\shopro\model\Activity::withTrashed()
                ->with('activityGoodsSkuPrice')
                ->where('id', 'in', $activity_ids)->select();
            $activities = array_column($activities, null, 'id');

            foreach ($grouponLogs as $key => $grouponLog) {
                if (isset($grouponLog['goods']) && $grouponLog['goods'] && isset($activities[$grouponLog['activity_id']])) {
                    $activity = $activities[$grouponLog['activity_id']];

                    // 拿到当前商品对应的活动规格
                    $currentGoodsActivitySkuPrices = [];
                    foreach ($activity['activityGoodsSkuPrice'] as $k => $skuPrice) {
                        if ($skuPrice['status'] == 'up' && $skuPrice['goods_id'] == $grouponLog['goods_id']) {
                            $currentGoodsActivitySkuPrices[] = $skuPrice;
                        }
                    }

                    // 当时参加活动真实销量
                    if ($currentGoodsActivitySkuPrices) {
                        $grouponLogs[$key]['goods']['sales'] = array_sum(array_column($currentGoodsActivitySkuPrices, 'sales'));
                    }
                    // 这个是购买时候的活动单价
                    if (isset($grouponLog['order']['first_item'])) {
                        $grouponLogs[$key]['goods']['price'] = $grouponLog['order']['first_item']['goods_price'];
                    }
                }
            }

            $logs['data'] = $grouponLogs;
        }

        return $logs;
    }


    public function activity() 
    {
        return $this->belongsTo(\addons\shopro\model\Activity::class, 'activity_id', 'id');
    }


    public function grouponLog()
    {
        return $this->hasMany(\addons\shopro\model\ActivityGrouponLog::class, 'groupon_id', 'id');
    }

    public function leader() {
        return $this->hasOne(\addons\shopro\model\ActivityGrouponLog::class, 'groupon_id', 'id')->where('is_leader', 1);
    }

    public function my() {
        $user = User::info();
        return $this->hasOne(\addons\shopro\model\ActivityGrouponLog::class, 'groupon_id', 'id')->where('user_id', ($user ? $user->id : 0));
    }

    public function goods()
    {
        return $this->belongsTo(\addons\shopro\model\Goods::class, 'goods_id', 'id');
    }
}
