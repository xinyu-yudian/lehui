<?php

namespace app\admin\model\shopro\store;

use think\Model;
use traits\model\SoftDelete;

class Store extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_store';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'area_text',
        'user_ids',
        'user_ids_list',
    ];

    public function getStatusTextAttr($value, $data)
    {
        return $data['status']  == '1' ? '启用中' : '禁用中';
    }


    public function getAreaTextAttr($value, $data)
    {
        $area_text = '';
        if(isset($data['service_type']) && $data['service_type'] === 'area') {
            $province_ids = [];
            if(!empty($data['service_province_ids']) ) {
                $province_ids = explode(',', $data['service_province_ids']);
            }

            $city_ids = [];
            if(!empty($data['service_city_ids'])) {
                $city_ids = explode(',', $data['service_city_ids']);
            }

            $area_ids = [];
            if(!empty($data['service_area_ids'])) {
                $area_ids = explode(',', $data['service_area_ids']);
            }
            $ids = array_merge($province_ids, $city_ids, $area_ids);
            if(!empty($ids)) {
                $areaArray = \app\admin\model\shopro\Area::where('id', 'in', $ids)->column('name');
                $area_text = implode(',', $areaArray);
            }
        }
        return $area_text;
    }

    public function getUserIdsAttr($value, $data)
    {
        $user_store = \think\Db::name('shopro_user_store')->where('store_id', $data['id'])->select();
        $user_ids = array_column($user_store, 'user_id');
        return $user_ids;
    }

    public function getUserIdsListAttr($value, $data)
    {
        $user_store = \think\Db::name('shopro_user_store')->where('store_id', $data['id'])->select();
        $user_ids_list = [];
        foreach($user_store as $us) {
            $user_ids_list[] = \app\admin\model\shopro\user\User::where('id', $us['user_id'])->field('id,nickname')->find();
        }
        return $user_ids_list;
    }
    
}
