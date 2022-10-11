<?php

namespace app\admin\model\shopro\app;

use think\Model;
use traits\model\SoftDelete;

class ScoreSkuPrice extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'shopro_score_goods_sku_price';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [

    ];

    public static function getCurrentGoodsIds()
    {
        $scoreGoodsIds = self::group('goods_id')->field('goods_id')->column('goods_id');
        return $scoreGoodsIds;
    }










}
