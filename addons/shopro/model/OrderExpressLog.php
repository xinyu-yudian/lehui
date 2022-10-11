<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use think\Db;

/**
 * 订单包裹记录
 */
class OrderExpressLog extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_order_express_log';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['updatetime'];
    // //列表动态隐藏字段
    // protected static $list_hidden = ['content', 'params', 'images', 'service_ids'];

    protected $append = [
        'status_name'
    ];

    public static function getStatusList() {
        return [
            0 => '暂无轨迹信息',
            1 => '已揽收',
            2 => '运输中',              // 在途中
            3 => '已签收',              // 签收
            4 => '问题件'
        ];
    }


    public function getStatusNameAttr($value, $data) {
        $status = substr($data['status'], 0, 1);

        $list = $this->getStatusList();
        return isset($list[$status]) ? $list[$status] : '';
    }
    
}
