<?php

namespace app\admin\controller\shopro\wechat;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;

/**
 * 微信管理
 *
 * @icon fa fa-circle-o
 */
class AutoReply extends Backend
{

    /**
     * Wechat模型对象
     * @var \app\admin\model\shopro\Wechat
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\wechat\Wechat;
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
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $type = $this->request->param('type');
            $total = $this->model
                ->where($where)
                ->where('type', $type)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('type', $type)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);
            $this->success('自动回复', null, $result);
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
                    $params['content'] = json_encode($params['content'], JSON_UNESCAPED_UNICODE);
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
    public function edit($id = null)
    {
        switch ($id) {
            case 'subscribe':
                $row = $this->model->get(['type' => 'subscribe']);
                if (!$row) {
                    $this->model->save([
                        'name' => '关注回复',
                        'type' => 'subscribe',
                        'content' => ''
                    ]);
                    $row = $this->model;
                }
                break;
            case 'default_reply':
                $row = $this->model->get(['type' => 'default_reply']);
                if (!$row) {
                    $this->model->save([
                        'name' => '默认回复',
                        'type' => 'default_reply',
                        'content' => ''
                    ]);
                    $row = $this->model;
                }
                break;
            default:
                $row = $this->model->get($id);
                break;
        }
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
                    $params['content'] = json_encode($params['content'], JSON_UNESCAPED_UNICODE);
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

}
