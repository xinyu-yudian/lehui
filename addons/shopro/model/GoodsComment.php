<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;

/**
 * 商品评价
 */
class GoodsComment extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_goods_comment';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        
    ];

    public static $typeAll = [
        'all' => ['code' => 'all', 'name' => '全部'],
        'images' => ['code' => 'images', 'name' => '有图'],
        'good' => ['code' => 'good', 'name' => '好评'],
        'moderate' => ['code' => 'moderate', 'name' => '中评'],
        'bad' => ['code' => 'bad', 'name' => '差评'],
    ];


    public function scopeImages($query) {
        return $query->whereNotNull('images')->where('images', '<>', '');
    }

    public function scopeGood($query) {
        return $query->where('level', 'in', [5, 4]);
    }

    public function scopeModerate($query) {
        return $query->where('level', 'in', [3, 2]);
    }

    public function scopeBad($query) {
        return $query->where('level', 1);
    }


    public static function getList ($params) {
        extract($params);
        $per_page = $per_page ?? 10;

        $goodsComments = self::with(['user' => function ($query) {
            $query->field('id, nickname, avatar');
        }])->where(['goods_id' => $goods_id, 'status' => 'show']);

        if ($type != 'all' && isset(self::$typeAll[$type])) {
            $goodsComments = $goodsComments->{$type}();
        }

        $goodsComments = $goodsComments->order('id', 'desc')->paginate($per_page)->toArray();
        $comments = $goodsComments['data'];
        // 处理评价用户昵称问题
        if ($comments) {
            foreach ($comments as $key => &$comment) {
                if ($comment['user']) {
                    $comment['user']['nickname'] = $comment['user']['nickname_hide'];
                    unset($comment['user']['nickname_hide']);
                }
            }
            $goodsComments['data'] = $comments;
        }

        return $goodsComments;
    }



    public function getImagesAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($value)) {
            $imagesArray = explode(',', $value);
            foreach ($imagesArray as &$v) {
                $v = cdnurl($v, true);
            }
            return $imagesArray;
        }
        return $imagesArray;
    }



    public function user()
    {
        return $this->belongsTo(\addons\shopro\model\User::class, 'user_id', 'id');
    }

}
