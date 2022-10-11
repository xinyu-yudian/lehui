<?php

namespace addons\shopro\validate\store;

use think\Validate;

class Apply extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require',
        'images' => 'require|array|min:1',
        'realname' => 'require',
        'phone' => 'require|regex:^1\d{10}$',
        'area_id' => 'require',
        'address' => 'require',
        'openhours' => 'require',
        'openweeks' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'name.require' => '门店名称必须填写',
        'images.require' => '门店图片必须上传',
        'images.array' => '门店图片必须上传',
        'images.min' => '门店图片必须上传',
        'realname.require' => '真实姓名必须填写',
        'phone.require' => '手机号必须填写',
        'phone.regex' => '手机号格式不正确',
        'area_id.require' => '所在区域必须选择',
        'address.require' => '详细地址必须填写',
        'openhours.require' => '营业时间必须填写',
        'openweeks.require' => '营业日期必须填写',
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
        'apply' => ['name', 'images', 'realname', 'phone', 'area_id', 'address', 'openhours', 'openweeks'],
    ];

}
