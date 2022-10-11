<?php

namespace app\admin\controller\shopro\app;

use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Live extends Backend
{
    
    /**
     * Live模型对象
     * @var \app\admin\model\shopro\app\Live
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\app\Live;
        $this->view->assign("liveStatusList", $this->model->getLiveStatusList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','name','room_id','live_status','starttime','endtime','anchor_name','share_img','createtime','updatetime']);
                
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        // 自动同步直播间
        \app\admin\model\shopro\app\Live::autoSyncLive();

        return $this->view->fetch();
    }
     /**
     * 直播详情
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        
        $this->view->assign("row", $row);
        $this->view->assign("goods", $row->goods);

        $liveLink = [];
        if ($row['live_status'] == \app\admin\model\shopro\app\Live::STATUS_LIVED) {
            // 直播已结束，显示 直播回放地址
            // 自动同步回放地址
            \app\admin\model\shopro\app\Live::autoSyncLiveLink($row);

            // 获取回放地址
            $liveLink = $row->links;
        }

        $this->view->assign("links", $liveLink);

        return $this->view->fetch();
    }


    public function select()
    {
        if ($this->request->isAjax()) {
            return $this->index();
        }
        return $this->view->fetch();
    }



    // 手动同步直播间
    public function syncLive () {
        // 手动同步直播间
        \app\admin\model\shopro\app\Live::syncLive();
        
        $this->success('同步成功');
    }
}
