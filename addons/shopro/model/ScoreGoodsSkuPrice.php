<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use addons\shopro\model\GoodsSku;
use addons\shopro\model\GoodsSkuPrice;
use traits\model\SoftDelete;

/**
 * 商品模型
 */
class ScoreGoodsSkuPrice extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_score_goods_sku_price';
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



    public static function getGoodsList($params)
    {
        $keyword = $params['keyword'] ?? '';

        // 获取所有积分商品
        $goods_ids = ScoreGoodsSkuPrice::field('goods_id')->where('status', 'up')->group('goods_id')->column('goods_id');
        
        $goods = Goods::where('id', 'in', $goods_ids);

        if (!empty($keyword)) {
            $goods = $goods->where('title|subtitle','like','%'. $keyword .'%');
        }

        $goods = $goods->with('scoreGoodsSkuPrice')->paginate(10);

        $data = $goods->items();
        foreach ($data as $key => $g) {
            if (count($g['score_goods_sku_price'])) {
                $g['price'] = $g['score_goods_sku_price'][0]['score'] . '积分';
                if ($g['score_goods_sku_price'][0]['price'] > 0) {
                    $g['price'] .= '+￥' . $g['score_goods_sku_price'][0]['price'];
                }

                // 销量
                $g['sales'] = array_sum(array_column($g['score_goods_sku_price'], 'sales'));
                $g['stock'] = array_sum(array_column($g['score_goods_sku_price'], 'stock'));
            }
            $data[$key] = $g;
        }
//         $collection = collection($goods->items());
//         $data = $collection->hidden(Goods::$list_hidden);
        $goods->data = $data;
        
        return $goods;
    }


    public static function getGoodsDetail($id)
    {
        $detail = Goods::where('id', $id)->find();
        
        if (!$detail) {
            new Exception('商品不存在或已下架');
        }
        // 增加商品附加数据, 活动默认全是 null
        $detail = $detail->append(['service', 'sku', 'coupons']);
        $detail->activity = null;
        $detail->activity_type = null;

        $score_sku_price = self::where([
            'status' => 'up',
        ])->select();

        $sku_price = GoodsSkuPrice::where([
            'goods_id' => $detail['id'],
            // 'status' => 'up',            // 商品规格的上下架，不控制积分规格的上下架
        ])->select();

        $new_sku_price = [];
        foreach ($sku_price as $s => $k) {
            foreach ($score_sku_price as $c) {
                if ($k['id'] == $c['sku_price_id']) {
                    $new_sku_price[$s] = $k;
                    $new_sku_price[$s]['stock'] = $c['stock'];
                    $new_sku_price[$s]['sales'] = $c['sales'];
                    $new_sku_price[$s]['price'] = $c['price'];
                    $new_sku_price[$s]['score'] = $c['score'];

                    $new_sku_price[$s]['price_text'] = $c['score'] . '积分';
                    if ($c['price'] > 0) {
                        $new_sku_price[$s]['price_text'] .= '+￥' . $c['price'];
                    }
                    
                    // 记录对应活动的规格的记录
                    $new_sku_price[$s]['item_goods_sku_price'] = $c;
                    break;
                }
            }
        }

        $sku_price = array_values($new_sku_price);

        if (!count($sku_price)) {
            new Exception('商品规格不存在或已下架');
        }

        $detail->price = $sku_price[0]['score'] . '积分';
        if ($sku_price[0]['price'] > 0) {
            $detail->price .= '+￥' . $sku_price[0]['price'];
        }
        $detail->sku_price = $sku_price;
        $detail->sales = array_sum(array_column($sku_price, 'sales'));
        $detail->stock = array_sum(array_column($sku_price, 'stock'));

        return $detail;
    }
}
