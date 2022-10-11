<?php

namespace addons\shopro\validate;

use think\Validate;

class Address extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'consignee' => 'require',
        'phone' => 'require|regex:^1\d{10}$',
        'address' => 'require',
        'area_text' => 'require',
        'latitude' => 'require',
        'longitude' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'consignee.require' => '收货人必须填写',
        'phone.require' => '手机号必须填写',
        'phone.regex' => '手机号格式不正确',
        'area_text.require' => '省市区必须选择',
        'address.require' => '详细地址必须填写',
        'latitude.require' => '请点击选择地理位置',
        'longitude.require' => '请点击选择地理位置',
    ];

    /**
     * 字段描述
     */
    protected $field = [

    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'edit' => ['consignee', 'phone', 'area_id', 'address','latitude','longitude'],
    ];

}
