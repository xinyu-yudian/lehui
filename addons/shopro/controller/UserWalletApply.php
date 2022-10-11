<?php

namespace addons\shopro\controller;


class UserWalletApply extends Base
{

    protected $noNeedLogin = ['rule'];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $this->success('提现记录', \addons\shopro\model\UserWalletApply::getList());
    }


    // 申请提现
    public function apply()
    {
        $type = $this->request->post('type');
        $money = $this->request->post('money');
        $apply = \think\Db::transaction(function () use ($type, $money) {
            try {
                return \addons\shopro\model\UserWalletApply::apply($type, $money);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        });
        if($apply) {
            $this->success('申请成功');            
        }
        $this->error('申请失败');
    }


    public function rule()
    {
        // 提现规则
        $config = \addons\shopro\model\UserWalletApply::getWithdrawConfig();
        $min = round(floatval($config['min']), 2);
        $max = round(floatval($config['max']), 2);
        $service_fee = floatval($config['service_fee']) * 100;
        $service_fee = round($service_fee, 1);      // 1 位小数
        $perday_amount = isset($config['perday_amount']) ? round(floatval($config['perday_amount']), 2) : 0;
        $perday_num = isset($config['perday_num']) ? round(floatval($config['perday_num']), 2) : 0;

        $rule = [
            'min' => $min,
            'max' => $max,
            'service_fee' => $service_fee,
            'perday_amount' => $perday_amount,
            'perday_num' => $perday_num,
            'methods' => $config['methods'] ?? []
        ];

        $this->success('提现规则', $rule);
    }
}
