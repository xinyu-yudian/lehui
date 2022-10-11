<?php

namespace addons\shopro\model;

use think\Db;
use think\Env;
use think\Model;

/**
 * 购物车模型
 */
class Work extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_work';

    //列表动态隐藏字段
//    protected static $listHidden = ['content', 'params', 'images', 'service_ids'];

    // 追加属性
    protected $append = [
    ];


    public static function getTimeList()
    {
        $where['status'] = 'normal';
        $work = self::where($where)->select();
        return $work;
    }
}
