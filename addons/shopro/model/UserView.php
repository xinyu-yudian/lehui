<?php

namespace addons\shopro\model;

use think\Model;
use traits\model\SoftDelete;
/**
 * 用户浏览足记模型
 */
class UserView extends Model
{
    use SoftDelete;

    protected $name = 'shopro_user_view';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $hidden = ['createtime', 'updatetime', 'deletetime'];

    // 追加属性
    protected $append = [
        
    ];

    // 添加浏览记录
    public static function addView($goodsDetail) {
        $user = User::info();
        if($user) {
            $view = self::where([
                'user_id' => $user->id,
                'goods_id' => $goodsDetail->id
            ])->find();
            if ($view) {
                $view->updatetime = time();
                $view->save();
            } else {
                self::create([
                    'user_id' => $user->id,
                    'goods_id' => $goodsDetail->id
                ]);
            }
        }

        Goods::where('id', $goodsDetail['id'])->update(['views' => \think\Db::raw('views+1')]);
    }

    public static function getGoodsList()
    {
        $user = User::info();
        return self::hasWhere('goods', ['deletetime' => null])->with('goods')->where(['user_id' => $user->id])->order('updatetime', 'DESC')->paginate(10);
    }

    public static function del($params) {
        extract($params);
        $user = User::info();
        //批量删除模式
        if (isset($goods_ids) && $user) {
            foreach ($goods_ids as $g) {
                self::get(['goods_id' => $g, 'user_id' => $user->id])->delete();
            }
            return false;
        }
    }


    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }


}
