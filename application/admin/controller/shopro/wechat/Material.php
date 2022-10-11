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
class Material extends Backend
{

    /**
     * Wechat模型对象
     * @var \app\admin\model\shopro\Wechat
     */
    protected $model = null;
    protected $noNeedLogin = [];
    protected $noNeedRight = ['detail'];
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
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $params = $this->request->param();
            extract($params);
            if ($type === 'text' || $type === 'link') {
                list($where, $sort, $order, $offset, $limit) = $this->buildparams();
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
            } else {
                $res = $this->getMaterialList($type, $offset = 0, $count = 20);
                $result = array("total" => $res['total_count'], "rows" => $res['item']);
            }
            $this->success('查看素材', null, $result);
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
        $row = $this->model->get($id);
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

    /**
     * 素材详情
     */
    public function detail($media_id)
    {
        $wechat = new \addons\shopro\library\Wechat('wxOfficialAccount');
        $app = $wechat->getApp();
        $resource = $app->material->get($media_id);
        if ($resource instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            return response($resource->getBody(), 200, ['Content-Length' => strlen($resource)])->contentType('audio/mp3');
        } else {
            // 小程序码获取失败
            $msg = isset($content['errcode']) ? $content['errcode'] : '-';
            $msg .= isset($content['errmsg']) ? $content['errmsg'] : '';
            \think\Log::write('wxacode-error' . $msg);

            $this->error('获取失败', $msg);
        }
        $this->success('素材详情', null, $resource);
    }

    /**
     * 选择素材
     */
    public function select()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->param();
            extract($params);
            if ($type === 'text' || $type === 'link') {
                list($where, $sort, $order, $offset, $limit) = $this->buildparams();
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
            } else {
                $res = $this->getMaterialList($type, $offset = 0, $count = 20);
                $result = array("total" => $res['total_count'], "rows" => $res['item']);
            }
            $this->success('选择素材', null, $result);
        }
    }

    private function getMaterialList($type, $offset, $count)
    {
        $wechat = new \addons\shopro\library\Wechat('wxOfficialAccount');
        $app = $wechat->getApp();
        $res = $app->material->list($type, $offset, $count);
        return $res;
    }

}
