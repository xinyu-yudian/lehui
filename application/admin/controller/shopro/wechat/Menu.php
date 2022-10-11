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
class Menu extends Backend
{

    /**
     * Wechat模型对象
     * @var \app\admin\model\shopro\wechat\Wechat
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
            $total = $this->model
                ->where($where)
                ->where('type', 'menu')
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('type', 'menu')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            $list = collection($list)->toArray();
            $currentMenu = $this->getCurrentMenu();
            $result = array("total" => $total, "rows" => $list, 'currentMenu' => $currentMenu);

            $this->success('查看菜单', null, $result);
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
                if ($this->request->get('act') === 'publish') {
                    $publish = $this->publishMenu($params['content']);
                    if (false == $publish['result']) {
                        return $this->error($publish['msg']);
                    }
                }
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
        $this->setWechatLinkEnv();
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
                if ($this->request->get('act') === 'publish') {
                    $publish = $this->publishMenu($params['content']);
                    if (!$publish['result']) {
                        $this->error($publish['msg']);
                    }
                }
                try {
                    $params['content'] = json_encode($params['content']);
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
        $this->setWechatLinkEnv();
        $this->assignconfig("row", $row);
        return $this->view->fetch();
    }



    /**
     * 复制
     */

    public function copy($id)
    {
        if ($id == 0) {
            $data = [
                'name' => '复制 当前菜单',
                'type' => 'menu',
                'content' => json_encode($this->getCurrentMenu())
            ];
        } else {
            $copyMenu = $this->model->get($id);
            $data = [
                'name' => '复制 ' . $copyMenu['name'],
                'type' => 'menu',
                'content' => $copyMenu['content']
            ];
        }

        $menu = new \app\admin\model\shopro\wechat\Wechat;
        $menu->allowField(true)->save($data);
        $this->success('复制成功');
    }

    /**
     * 发布菜单
     */
    public function publish($id)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $publish = $this->publishMenu(json_decode($row->content, true));
        if (!$publish['result']) {
            return $this->error($publish['msg']);
        }
        return $this->success('发布成功');
    }

    //发布指定菜单
    private function publishMenu($buttons)
    {
        $wechat = new \addons\shopro\library\Wechat('wxOfficialAccount');
        try {
            $res = $wechat->menu('create', $buttons);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'ip')) {
                return [
                    'result' => false,
                    'msg' => '请将当前IP地址加入公众号后台IP白名单'
                ];
            }
        }
        if ($res['errcode'] !== 0) {
            return [
                'result' => false,
                'msg' => '请检查您的菜单格式'
            ];
        }
        return ['result' => true, 'msg' => '发布成功'];
    }

    //获取当前菜单
    private function getCurrentMenu()
    {
        $wechat = new \addons\shopro\library\Wechat('wxOfficialAccount');
        $currentMenuInfo = $wechat->menu('current');
        if (isset($currentMenuInfo['selfmenu_info']['button'])) {
            $buttons = $currentMenuInfo['selfmenu_info']['button'];
            foreach($buttons as &$b) {
                if(isset($b['sub_button'])) {
                    $b['sub_button'] = $b['sub_button']['list'];
                }
            }
            return $buttons;
        } else {
            return [];
        }
    }

    //设置微信环境域名和appid
    private function setWechatLinkEnv()
    {
        $shopro = json_decode(\app\admin\model\shopro\Config::get(['name' => 'shopro'])->value, true);
        $wxMiniProgram = json_decode(\app\admin\model\shopro\Config::get(['name' => 'wxMiniProgram'])->value, true);
        $this->assignconfig('shopro', $shopro);
        $this->assignconfig('wxMiniProgram', $wxMiniProgram);
    }
}
