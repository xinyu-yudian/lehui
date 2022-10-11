<?php

namespace app\admin\model\shopro\order;

use think\Model;

class AftersaleLog extends Model
{

    // 表名
    protected $name = 'shopro_order_aftersale_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'images_arr'
    ];



    public function getImagesArrAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($data['images'])) {
            $imagesArray = explode(',', $data['images']);
            return $imagesArray;
        }
        return $imagesArray;
    }

}
