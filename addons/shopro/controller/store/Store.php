<?php

namespace addons\shopro\controller\store;

use addons\shopro\exception\Exception;
use addons\shopro\model\Store as ModelStore;

class Store extends Base
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];


    public function index()
    {
        $params = $this->request->get();
        $store = ModelStore::info();
        if (!$store) {
            $this->error('门店不存在');
        }

        $this->success('获取成功', $store);
    }
    
}
