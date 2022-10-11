<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 配置模型
 */
class Richtext extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_richtext';
    // 追加属性
    protected $append = [
    ];


    public function getContentAttr($value, $data)
    {
        $content = $data['content'];
        $content = str_replace("<img src=\"/uploads", "<img style=\"width: 100%;!important\" src=\"" . cdnurl("/uploads", true), $content);
        $content = str_replace("<video src=\"/uploads", "<video style=\"width: 100%;!important\" src=\"" . cdnurl("/uploads", true), $content);
        return $content;
    }

}
