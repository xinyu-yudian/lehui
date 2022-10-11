<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use think\Db;
use traits\model\SoftDelete;

/**
 * 门店
 */
class Store extends Model
{
    use SoftDelete;

    // 表名,不含前缀
    protected $name = 'shopro_store';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    
    protected $hidden = ['deletetime'];

    // 追加属性
    protected $append = [
        'openweeks_arr',
        'distance_text',
        'image_first'
    ];


    /**
     * 返回当前门店信息
     */
    public static function info()
    {
        $store = session('current_oper_store');
        return $store ? : null;
    }

    public function scopeShow($query) {
        return $query->where('status', 1);
    }


    public function getOpenweeksArrAttr($value, $data) {
        return $data['openweeks'] ? array_map('intval', explode(',', $data['openweeks'])) : [];
    }


    public function getImageFirstAttr($value, $data)
    {
        return $this->images[0] ? : '';
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

    public function getDistanceTextAttr($value, $data) {
        $distance_text = '';
        $distance =  $data['distance'] ?? 0;

        switch (true) {
            case $distance >= 1000;
                $distance_text = round(($distance / 1000), 2) . 'km';
                break;
            default :
                $distance_text = $distance . 'm';
                break;
            
        }

        return $distance_text;
    }


    public function userStore() {
        return $this->hasMany(UserStore::class, 'store_id', 'id');
    }
}
