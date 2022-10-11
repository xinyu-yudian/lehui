<?php

namespace app\admin\controller\shopro\goods;


use app\admin\controller\shopro\Base;
use think\Db;
/**
 * 商品评价
 *
 * @icon fa fa-circle-o
 */
class Comment extends Base
{

    /**
     * GoodsComment模型对象
     * @var \app\admin\model\shopro\goods\Comment
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\goods\Comment;
        $this->view->assign("statusList", $this->model->getStatusList());
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
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $nobuildfields = ['goods_title', 'comment_status'];
            list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);


            $total = $this->buildSearch()
                ->where($where)
                ->count();

            $list = $this->buildSearch()
                ->with('goods')
                ->with('user')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();


            $result = [
                "total" => $total,
                "rows" => $list
            ];

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }


    private function buildSearch()
    {
        $filter = $this->request->get("filter", '');
        $filter = (array)json_decode($filter, true);
        $filter = $filter ? $filter : [];

        $goods_title = isset($filter['goods_title']) ? $filter['goods_title'] : '';
        $status = isset($filter['comment_status']) ? $filter['comment_status'] : 'all';

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $comment = $this->model;

        // 商品名称
        if ($goods_title) {
            $comment = $comment->whereExists(function ($query) use ($goods_title, $tableName) {
                $goodsTableName = (new \app\admin\model\shopro\goods\Goods())->getQuery()->getTable();

                $query = $query->table($goodsTableName)->where($goodsTableName . '.id=goods_id');

                $query = $query->where('title', 'like', "%{$goods_title}%");

                return $query;
            });
        }

        if ($status != 'all') {
            $comment = $comment->where('status', $status);
        }

        return $comment;
    }

    public function setStatus($ids, $status) {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $v->status = $status;
                    $count += $v->save();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were updated'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

}
