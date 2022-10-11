<?php

namespace app\admin\controller\shopro\activity;

use addons\shopro\library\traits\Groupon as TraitsGroupon;
use app\common\controller\Backend;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Groupon extends Backend
{
    use TraitsGroupon;

    /**
     * Groupon模型对象
     * @var \app\admin\model\shopro\activity\Groupon
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\activity\Groupon;
        $this->view->assign("statusList", $this->model->getStatusList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 团列表
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            // 检测队列
            checkEnv('queue');
            
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }

            $sort = $this->request->get("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
            $order = $this->request->get("order", "DESC");
            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);

            $total = $this->buildSearch()
                ->order($sort, $order)
                ->count();

            $list = $this->buildSearch()
                ->with(['goods', 'user', 'grouponLog'])
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return $this->success('操作成功', null, $result);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function detail($id = null) {
        if ($this->request->isAjax()){
            $row = $this->model->with(['goods', 'user', 'grouponLog'])
                ->where('id', $id)
                ->find();

            if (!$row) {
                $this->error(__('No Results were found'));
            }

            return $this->success('获取成功', null, $row);
        }
        $this->assignconfig('id', $id);
        return $this->view->fetch();
    }


    // 增加虚拟成团人数
    public function addFictitious($id = null) {
        $row = $this->model->where('id', $id)->find();

        $avatar = $this->request->post('avatar', '');
        $nickname = $this->request->post('nickname', '');

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        if ($row['status'] != 'ing' || $row['current_num'] > $row['num']) {
            $this->error('团已完成或已失效');
        }

        // 增加人数
        $user = ['avatar' => $avatar, 'nickname' => $nickname];
        $this->finishFictitiousGroupon($row, 1, [$user]);

        // 重新获取团信息
        $row = $this->model->with(['goods', 'user', 'grouponLog'])
                ->where('id', $id)
                ->find();

        return $this->success('操作成功', null, $row);
    }


    // 解散团
    public function invalidGroupon ($id = null) {
        $row = $this->model->where('id', $id)->find();

        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if ($row['status'] != 'ing') {
            $this->error('团已完成或已失效');
        }

        // 解散团，并退款
        $this->invalidRefundGroupon($row);

        // 重新获取团信息
        $row = $this->model->with(['goods', 'user', 'grouponLog'])
                ->where('id', $id)
                ->find();

        return $this->success('解散成功', null, $row);
    }


    // 构建查询条件
    private function buildSearch()
    {
        $search = $this->request->get("search", '');        // 关键字
        $status = $this->request->get("status", 'all');
        $activity_id = $this->request->get("activity_id", 0);

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $groupon = $this->model;

        if ($search) {
            // 模糊搜索字段
            $groupon = $groupon->where(function ($query) use ($search, $tableName) {
                $query->where(function ($query) use ($search, $tableName) {
                    $query->whereExists(function ($query) use ($search, $tableName) {
                        $goodsName = (new \app\admin\model\shopro\goods\Goods())->getQuery()->getTable();

                        $query->table($goodsName)->where($goodsName . '.id=' . $tableName . 'goods_id')
                            ->where('title', 'like', "%{$search}%");
                    });
                })
                ->whereOr(function ($query) use ($search, $tableName) {                  // 用户
                    $query->whereExists(function ($query) use ($search, $tableName) {
                        $userTableName = (new \app\admin\model\User())->getQuery()->getTable();

                        $query->table($userTableName)->where($userTableName . '.id=' . $tableName . 'user_id')
                            ->where(function ($query) use ($search) {
                                $query->where('nickname', 'like', "%{$search}%")
                                    ->whereOr('mobile', 'like', "%{$search}%");
                            });
                    });
                });
            });
        }

        // 活动类型
        if ($activity_id) {
            $groupon = $groupon->where('activity_id', $activity_id);
        }
        // 活动状态
        if ($status != 'all') {
            $status = $status == 'finish' ? ['finish', 'finish-fictitious'] : [$status];

            $groupon = $groupon->where('status', 'in', $status);
        }

        return $groupon;
    }
}
