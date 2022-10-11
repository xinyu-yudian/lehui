<?php

namespace app\admin\model\join\lottery;

use think\Model;


class Userlist extends Model
{





    // 表名
    protected $name = 'join_lottery_userlist';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'is_award_text',
        'awardtime_text'
    ];



    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getIsAwardList()
    {
        return ['0' => __('Is_award 0'), '1' => __('Is_award 1'), '2' => __('Is_award 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsAwardTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_award']) ? $data['is_award'] : '');
        $list = $this->getIsAwardList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getAwardtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['awardtime']) ? $data['awardtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setAwardtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function lottery()
    {
        return $this->belongsTo('app\admin\model\Lottery', 'lottery_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function award()
    {
        return $this->belongsTo('app\admin\model\Award', 'award_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
