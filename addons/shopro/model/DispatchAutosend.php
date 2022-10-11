<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Model;
use traits\model\SoftDelete;
/**
 * dispatch 自动发货模板
 */
class DispatchAutosend extends Model
{
    use SoftDelete;

    protected $name = 'shopro_dispatch_autosend';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $hidden = ['createtime', 'updatetime', 'deletetime'];

    // 追加属性
    protected $append = [
        
    ];


    /**
     * 处理自动发货内容
     */
    public static function getParamsContent($content) {
        $contents = json_decode($content, true);
        $contents = $contents ? : [];
        $newContents = [];
        foreach ($contents as $key => $content) {
            foreach ($content as $k => $v) {
                $newContents[] = [
                    'name' => $k,
                    'value' => $v
                ];
            } 
        }

        return json_encode($newContents);
    }


    public static function getParamsContentText($content) {
        // 这里的params content 是 getParamsContent 的处理结果，现在给他转成字符串
        $contents = json_decode($content, true);
        $contents = $contents ?: [];
        $newContents = '';
        foreach ($contents as $key => $content) {
            if (isset($content['name'])) {
                // 正常格式
                $newContents .= $content['name'] . '：'. $content['value'] . ' ';
            } else {
                // 非正常格式
                if (is_array($content)) {
                    $content_for = str_replace('{', '', json_encode($content));
                    $content_for = str_replace('}', '', $content_for);
                    $content_for = preg_replace("/(\"){1}/", " ", $content_for);        // 去除 "
                    $content_for = preg_replace("/\s(?=\s)/", "\\1", $content_for);     // 去除多余空格
                    $newContents .= $content_for;
                } else {
                    $newContents .= $content;
                }
            }
        }

        return $newContents;
    }
}
