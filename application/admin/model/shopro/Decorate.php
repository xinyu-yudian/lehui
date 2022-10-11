<?php

namespace app\admin\model\shopro;

use think\Model;
use traits\model\SoftDelete;

class Decorate extends Model
{

    use SoftDelete;

    // 表名
    protected $name = 'shopro_decorate';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'platform_text'
    ];



    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getPlatformList()
    {
        return ['H5' => __('Platform h5'), 'wxOfficialAccount' => __('Platform wxofficialaccount'), 'wxMiniProgram' => __('Platform wxminiprogram'), 'App' => __('Platform app')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    // 模板列表截图是本地图
    // public function getImageAttr($value, $data)
    // {
    //     if (!empty($value)) return cdnurl($value, true);
    // }

    public function getPlatformTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['platform']) ? $data['platform'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getPlatformList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    protected function setPlatformAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


}
