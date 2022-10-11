<?php

namespace app\admin\model\shopro\user;

use think\Model;

/**
 * 会员钱包日志模型
 */
class WalletLog extends Model
{

    // 表名
    protected $name = 'shopro_user_wallet_log';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'oper'
    ];

    public function getOperAttr($value, $data)
    {
        return \addons\shopro\library\Oper::get($data['oper_type'], $data['oper_id']);
    }
    
    public function getWalletAttr($value, $data)
    {
        if($data['wallet_type'] === 'score') {
            return intval($data['wallet']);
        }else {
            return $data['wallet'];
        }
    }

    public function getBeforeAttr($value, $data)
    {
        if($data['wallet_type'] === 'score') {
            return intval($data['before']);
        }else {
            return $data['before'];
        }
    }

    public function getAfterAttr($value, $data)
    {
        if($data['wallet_type'] === 'score') {
            return intval($data['after']);
        }else {
            return $data['after'];
        }
    }
}
