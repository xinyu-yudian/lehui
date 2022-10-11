<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;

/**
 * 活动模型
 */
class Activity extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_activity';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime', 'goods_ids'];
    //列表动态隐藏字段
//    protected static $listHidden = ['content', 'params', 'images', 'service_ids'];

    // 追加属性
    protected $append = [
        'status_code'
    ];

    public static function getTypeList()
    {
        return ['seckill' => '秒杀', 'groupon' => '拼团', 'full_reduce' => '满额立减', 'full_discount' => '满额折扣', 'free_shipping' => '满额包邮'];
    }

    /**
     * 格式化 discount 折扣为具体优惠标签
     *
     * @param string $activity_type
     * @param array $ruleData
     * @return string
     */
    public static function formatDiscountTags($activity_type, $ruleData = []) {
        if (in_array($activity_type, ['full_reduce', 'full_discount'])) {
            $tag = '满' . $ruleData['full'] . ($ruleData['type'] == 'money' ? '元' : '件');
            $tag .= $activity_type == 'full_reduce' ? '减' . $ruleData['discount'] : $ruleData['discount'] . '折';
        } else if ($activity_type == 'free_shipping') {
            $tag = '满' . $ruleData['full_num'] . ($ruleData['type'] == 'money' ? '元' : '件') . '包邮';
        }

        return $tag;
    }


    /**
     * 格式化 rules 里面的活动规则为 tags
     *
     * @param string $activity_type
     * @param array $rules
     * @return array
     */
    public static function formatRuleTags($activity_type, $rules) {
        $tags = [];
        if (in_array($activity_type, ['full_reduce', 'full_discount'])) {
            $discounts = $rules['discounts'] ?? [];

            foreach ($discounts as $discount) {
                $tags[] = self::formatDiscountTags($activity_type, [
                    'type' => $rules['type'],
                    'full' => $discount['full'],
                    'discount' => $discount['discount']
                ]);
            }
        } else if ($activity_type == 'free_shipping') {
            $tags[] = self::formatDiscountTags($activity_type, [
                'type' => $rules['type'],
                'full_num' => $rules['full_num'],
            ]);
        }

        return $tags;
    } 


    // 根据时间计算活动状态
    public static function getStatusCode($data) {
        $status_code = 'end';
        $deletetime = $data['deletetime'] ?? null;
        $starttime = $data['starttime'] ?? null;
        $endtime = $data['endtime'] ?? null;

        if (!$starttime || !$endtime) {
            return $status_code;
        }

        if ($deletetime) {
            $status_code = 'end';
        } else {
            if ($starttime > time()) {
                $status_code = 'waiting';
            } else if ($starttime < time() && $endtime > time()) {
                $status_code = 'ing';
            } else if ($endtime < time()) {
                $status_code = 'end';
            }
        }

        return $status_code;
    }


    public function scopeNostart($query) {
        return $query->where('starttime', '>', time());
    }


    public function scopeIng($query) {
        return $query->where('starttime', '<', time())->where('endtime', '>', time());
    }


    public function scopeEnded($query) {
        return $query->where('endtime', '<', time());
    }

    protected function getRulesAttr($value, $data)
    {
        $rules = json_decode($value, true);

        if (isset($rules['discounts']) && $rules['discounts']) {
            // 处理展示优惠，full 从小到大
            $discounts = $rules['discounts'] ?? [];
            $discountsKeys = array_column($discounts, null, 'full');
            ksort($discountsKeys);
            $rules['discounts'] = array_values($discountsKeys);        // 优惠按照 full 从小到大排序
        }

        return $rules;
    }


    public function getStatusCodeAttr($value, $data) {
        return self::getStatusCode($data);
    }

    public function activityGoodsSkuPrice() {
        return $this->hasMany(\addons\shopro\model\ActivityGoodsSkuPrice::class, 'activity_id', 'id');
    }
}
