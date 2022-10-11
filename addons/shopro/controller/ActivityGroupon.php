<?php

namespace addons\shopro\controller;

/**
 * 拼团开的团
 *
 */

class ActivityGroupon extends Base
{
    protected $noNeedLogin = ['index', 'detail'];
    protected $noNeedRight = ['*'];


    /**
     * 根据商品 id 获取正在拼的团
     */
    public function index() {
        $params = $this->request->get();

        $this->success('团列表', \addons\shopro\model\ActivityGroupon::getActivityGroupon($params));
    }


    public function detail () {
        $id = $this->request->get('id');

        $this->success('团详情', \addons\shopro\model\ActivityGroupon::getActivityGrouponDetail($id));
    }


    public function myGroupon () {
        $type = $this->request->get('type', 'all');

        $this->success('我的拼团', \addons\shopro\model\ActivityGroupon::getMyGroupon($type));
    }
}
