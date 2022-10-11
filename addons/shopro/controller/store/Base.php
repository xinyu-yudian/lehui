<?php

namespace addons\shopro\controller\store;

use addons\shopro\exception\Exception;
use addons\shopro\controller\Base as AddonsBase;
use addons\shopro\model\Store;
use addons\shopro\model\User;
use addons\shopro\model\UserStore;

class Base extends AddonsBase
{
    public function _initialize()
    {
        parent::_initialize();

        // 验证登录用户是否可以访问门店接口
        $this->checkUserStore();
    }


    /**
     * 检测用户管理的是否有门店
     */
    private function checkUserStore() {
        // 获取当前用户的门店
        $user = User::info();
        $store_id = $this->request->param('store_id');

        if (!$store_id) {
            $this->error('请选择门店');
        }

        $userStore = UserStore::with('store')->where('user_id', $user->id)->where('store_id', $store_id)->find();
        if (!$userStore || !$userStore->store) {
            $this->error('权限不足');
        }

        $store = $userStore->store->toArray();

        if (!$store['status']) {
            $this->error('门店已被禁用');
        }

        // 存 session 本次请求有效
        session('current_oper_store', $store);
    }
}
