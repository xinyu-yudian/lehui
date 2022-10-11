<?php

namespace app\admin\model\shopro;

use think\Model;
use traits\model\SoftDelete;

class UserWalletApply extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'shopro_user_wallet_apply';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'apply_type_text',
        'status_text',
        'apply_info_text'
    ];



    public function getApplyTypeList()
    {
        return ['bank' => __('Apply_type bank'), 'wechat' => __('Apply_type wechat'), 'alipay' => __('Apply_type alipay')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '-1' => __('Status -1')];
    }


    public function getApplyTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['apply_type']) ? $data['apply_type'] : '');
        $list = $this->getApplyTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getApplyInfoTextAttr($value, $data)
    {
        $value = $data['apply_info'] ?? '';
        $value = $value ? json_decode($value, true) : '-';
        return $value;
    }

    /**
     * 提现信息
     */
    public function getApplyInfoAttr($value, $data)
    {
        $value = isset($data['apply_info']) ? $data['apply_info'] : $value;
        return json_decode($value, true);
    }

    /**
     * 获取日志字段数组
     * @param   string $value
     * @param   array  $data
     * @return  array
     */
    public function getLogAttr($value, $data)
    {
        $value = array_filter((array)json_decode($value, true));
        return (array)$value;
    }

    /**
     * 设置日志字段
     * @param mixed $value
     * @return string
     */
    public function setLogAttr($value)
    {
        $value = is_object($value) || is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        return $value;
    }

    public function user()
    {
        return $this->belongsTo(\app\admin\model\User::class, 'user_id');
    }
}
