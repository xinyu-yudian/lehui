<?php

namespace app\admin\controller\shopro\dispatch;

use app\common\controller\Backend;
use app\admin\model\shopro\dispatch\Express as ExpressModel;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;


/**
 * 快递物流
 *
 * @icon fa fa-circle-o
 */
class Express extends Backend
{

    /**
     * Express模型对象
     * @var \app\admin\model\shopro\dispatch\Express
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\dispatch\Dispatch;
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
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->where('type', 'express')
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('type', 'express')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id', 'name', 'type', 'type_ids', 'express', 'createtime', 'updatetime']);
                $row->express = $this->getExpress($row);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return $this->success('物流快递', null, $result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = json_decode($params['data'], true);
                $result = false;
                Db::startTrans();
                try {
                    $express = $params['express'];
                    $type_ids = [];
                    foreach ($express as $k => $e) {
                        $e['type'] = $params['type'];
                        $expressModel = new ExpressModel();
                        $expressModel->allowField(true)->save($e);
                        array_push($type_ids, $expressModel->id);
                    }
                    $params['type_ids'] = implode(',', $type_ids);
                    $params['type'] = 'express';
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }


        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = json_decode($params['data'], true);
                $result = false;
                Db::startTrans();
                try {
                    $express = $params['express'];
                    $type_ids = [];
                    foreach ($express as $k => $e) {
                        $e['type'] = $params['type'];
                        $expressModel = new ExpressModel();
                        if (isset($e['id'])) {
                            $expressModel = $expressModel->get($e['id']);
                            $expressModel->allowField(true)->save($e);
                        } else {
                            $expressModel->allowField(true)->save($e);
                        }
                        array_push($type_ids, $expressModel->id);
                    }
                    $oldTypeIds = explode(',', $row['type_ids']);
                    foreach ($oldTypeIds as $id) {
                        if (!in_array($id, $type_ids)) {
                            ExpressModel::destroy($id);
                        }
                    }

                    $row->type_ids = implode(',', $type_ids);
                    $row->type = 'express';
                    $row->name = $params['name'];
                    $row->save();
                    $result = true;
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $row->express = $this->getExpress($row);
        $this->assignconfig("row", $row);
        return $this->view->fetch();
    }

    /**
     * 回收站
     */
    public function recyclebin()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->onlyTrashed()
                ->where($where)
                ->where('type', 'express')
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->onlyTrashed()
                ->where($where)
                ->where('type', 'express')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


    private function getExpress($data)
    {
        if ($data['type'] === 'express') {
            return \app\admin\model\shopro\dispatch\Express::where('id', 'in', $data['type_ids'])->order('weigh desc, id asc')->select();
        }
        return null;
    }
}
