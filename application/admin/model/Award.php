<?php

namespace app\admin\model;

use think\Model;
use traits\model\SoftDelete;

class Award extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'award';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'lotterylist_text'
    ];
    

    
    public function getLotterylistList()
    {
        return ['0' => __('Lotterylist 0'), '1' => __('Lotterylist 1')];
    }


    public function getLotterylistTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lotterylist']) ? $data['lotterylist'] : '');
        $list = $this->getLotterylistList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
