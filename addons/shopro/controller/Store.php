<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use addons\shopro\model\Store as ModelStore;
use addons\shopro\model\User;
use addons\shopro\model\UserStore;

class Store extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $user = User::info();
        $userStore = UserStore::where('user_id', $user->id)->select();
        $store_id_arr = array_column($userStore, 'store_id');

        $stores = [];
        if ($store_id_arr) {
            $stores = ModelStore::show()->where('id', 'in', $store_id_arr)->select();
        }
        
        $this->success('获取门店列表', $stores);
    }
    
}
