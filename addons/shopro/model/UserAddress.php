<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\model\Area;
use addons\shopro\exception\Exception;
use traits\model\SoftDelete;

/**
 * 用户地址模型
 */
class UserAddress extends Model
{
    use SoftDelete;

    protected $name = 'shopro_user_address';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $hidden = ['createtime', 'updatetime', 'deletetime'];

    // 追加属性
    protected $append = [];

    public static function getUserAddress()
    {
        $user = User::info();
        return self::all([
            'user_id' => $user->id,
            'deletetime' => null
        ]);
    }

    public static function getUserDefaultAddress()
    {
        $user = User::info();
        $default = self::get([
            'user_id' => $user->id,
            'deletetime' => null,
            'is_default' => '1'
        ]);

        return $default;
    }

    public static function edit($params)
    {
        $user = User::info();
        extract($params);
        $areaNameArray = explode("-", $area_text);
        try {
            $province = Area::get(['name' => $areaNameArray[0], 'level' => 1]);
            $city = Area::get(['name' => $areaNameArray[1], 'level' => 2]);
            $area = Area::get(['name' => $areaNameArray[2], 'pid' => $city->id, 'level' => 3]);
            if(!$province || !$city || !$area) {
                new Exception('暂不支持,请手动选择行政区');
            }
        } catch (\Exception $e) {
            new Exception('暂不支持,请手动选择行政区');
        }
        $is_default = (isset($is_default) && $is_default) ? '1' : '0';
        if ($is_default) {
            self::where(['user_id' => $user->id, 'is_default' => '1'])->update(['is_default' => '0']);
        }

        if (!isset($id) || $id == 0) {
            $edit = self::create([
                'consignee' => $consignee,
                'phone' => $phone,
                'province_id' => $province->id,
                'province_name' => $province->name,
                'city_id' => $city->id,
                'city_name' => $city->name,
                'area_id' => $area->id,
                'area_name' => $area->name,
                'is_default' => $is_default,
                'latitude' => (isset($latitude) && $latitude) ? $latitude : null,
                'longitude' => (isset($longitude) && $longitude) ? $longitude : null,
                'user_id' => $user->id,
                'address' => $address
            ]);
        } else {
            $edit = self::update([
                'consignee' => $consignee,
                'phone' => $phone,
                'province_id' => $province->id,
                'province_name' => $province->name,
                'city_id' => $city->id,
                'city_name' => $city->name,
                'area_id' => $area->id,
                'area_name' => $area->name,
                'is_default' => $is_default,
                'latitude' => (isset($latitude) && $latitude) ? $latitude : null,
                'longitude' => (isset($longitude) && $longitude) ? $longitude : null,
                'user_id' => $user->id,
                'address' => $address,
                'id' => $id
            ]);
        }
        return $edit;
    }

    public static function info($params)
    {
        $user = User::info();
        extract($params);
        return self::get(['id' => $id, 'user_id' => $user->id]);
    }

    public static function del($params)
    {
        $user = User::info();
        extract($params);
        return self::get(['id' => $id, 'user_id' => $user->id])->delete();
    }
}
