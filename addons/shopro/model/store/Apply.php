<?php

namespace addons\shopro\model\store;

use think\Model;
use addons\shopro\model\User;
use addons\shopro\model\Area;

/**
 * 门店申请
 */
class Apply extends Model
{
    // 表名,不含前缀
    protected $name = 'shopro_store_apply';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = [];

    protected $append = [
        'status_name',
        'images_original'
    ];


    /**
     * 门店申请详情
     *
     * @return object
     */
    public static function info() {
        $user = User::info();

        // 查询审核未通过的或者驳回的门店申请信息
        $apply = self::where('user_id', $user->id)->where('status', 'in', [-1, 0])->find();

        return $apply;
    }


    /**
     * 门店申请
     */
    public static function apply($params) {
        $user = User::info();

        extract($params);

        $area = Area::get($area_id);
        $city = Area::get($area->pid);
        $province = Area::get($city->pid);

        $apply = self::info();

        $data['user_id'] = $user->id;
        $data['name'] = $name;
        $data['images'] = join(',', $images);
        $data['realname'] = $realname;
        $data['phone'] = $phone;
        $data['province_name'] = $province->name;
        $data['city_name'] = $city->name;
        $data['area_name'] = $area->name;
        $data['province_id'] = $province->id;
        $data['city_id'] = $city->id;
        $data['area_id'] = $area->id;
        $data['address'] = $address;
        $data['latitude'] = $latitude;
        $data['longitude'] = $longitude;
        $data['openhours'] = $openhours;
        $data['openweeks'] = $openweeks;
        $data['status'] = 0;
        
        if ($apply) {
            $data['apply_num'] = $apply->apply_num + 1;
            $apply->save($data);
        } else {
            $data['apply_num'] = 1;
            $apply = new self();
            $apply->save($data);
        }
        
        return $apply;
    }



    /* -------------------------- 访问器 ------------------------ */
    public function getStatusNameAttr($value, $data) {
        switch($data['status']) {
            case -1: 
                $status_name = '已拒绝';
                break;
            case 0:
                $status_name = '待审核';
                break;
            case 1:
                $status_name = '已通过';
                break;
            default :
                $status_name = '';
        }

        return $status_name;
    }


    public function getImagesAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($value)) {
            $imagesArray = explode(',', $value);
            foreach ($imagesArray as &$v) {
                $v = cdnurl($v, true);
            }
        }
        return $imagesArray;
    }


    public function getImagesOriginalAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($data['images'])) {
            $imagesArray = explode(',', $data['images']);
        }
        return $imagesArray;
    }

    /* -------------------------- 模型关联 ------------------------ */

    
    /* -------------------------- 模型关联 ------------------------ */
}
