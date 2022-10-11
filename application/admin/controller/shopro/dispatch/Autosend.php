<?php

namespace app\admin\controller\shopro\dispatch;

use app\common\controller\Backend;
use app\admin\model\shopro\dispatch\Autosend as AutosendModel;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;

/**
 * 自动发货
 *
 * @icon fa fa-circle-o
 */
class Autosend extends Backend
{

    /**
     * Express模型对象
     * @var \app\admin\model\shopro\dispatch\Autosend
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
        if ($this->request->isAjax())
        {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->where($where)
                    ->where('type', 'autosend')
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->where($where)
                    ->where('type', 'autosend')
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','name','type','type_ids', 'autosend', 'createtime','updatetime']);
                $row->autosend = $this->getAutosend($row);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return $this->success('自动发货', null, $result);
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
                    $type_ids = [];
                    $autosendModel = new AutosendModel();
                    if($params['type'] === 'params') {
                        $params['content'] = json_encode($params['content']);
                    }
                    $autosendModel->allowField(true)->save($params);
                    $params['type_ids'] = $autosendModel->id;
                    $params['type'] = 'autosend';
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
        $row->autosend = $this->getAutosend($row);

        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = json_decode($params['data'], true);
                $result = false;
                Db::startTrans();
                try {
                    $autosendModel = new AutosendModel();
                    $autosend = $autosendModel->get($row->type_ids);
                    if($params['type'] === 'params') {
                        $params['content'] = json_encode($params['content']);
                    }
                    $autosend->allowField(true)->save($params);
                    $params['type'] = 'autosend';
                    $params['type_ids'] = $autosend->id;
                    $result = $row->allowField(true)->save($params);
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
                ->where('type', 'autosend')
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->onlyTrashed()
                ->where($where)
                ->where('type', 'autosend')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

        
    private function getAutosend($data)
    {
        if($data['type'] == 'autosend' ) {
            $autosend = \app\admin\model\shopro\dispatch\Autosend::where('id', $data['type_ids'])->find();
            if($autosend && $autosend['type'] === 'params') {
                $autosend['content'] = json_decode($autosend['content'], true);
            }
            return $autosend;
        }
        return null;
    }
   
}
