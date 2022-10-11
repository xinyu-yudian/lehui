<?php

namespace addons\shopro\controller;

use app\admin\model\Rechargerule;
use addons\shopro\exception\Exception;

class UserWalletLog extends Base
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();
        $wallet_type = $params['wallet_type'] ?? 'money';

        if (!in_array($wallet_type, ['money', 'score'])) {
            $this->error('参数错误');
        }

        $this->success(($wallet_type == 'money' ? '钱包记录' : '积分记录'), \addons\shopro\model\UserWalletLog::getList($params));
    }
	
	public function getrechargerule()
	{
	
		$params = $this->request->get();
		$this->success('获取充值赠送数量', Rechargerule::where('status', '1')->select());
		
	}
}
