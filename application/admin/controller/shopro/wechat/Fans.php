<?php

namespace app\admin\controller\shopro\wechat;

use app\common\controller\Backend;

/**
 * 微信管理
 *
 * @icon fa fa-circle-o
 */
class Fans extends Backend
{

    /**
     * Wechat模型对象
     * @var \app\admin\model\shopro\wechat\Fans
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\wechat\Fans;
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
            $searchWhere = $this->request->request('searchWhere');
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->whereOr('id', '=', $searchWhere)
                ->whereOr('nickname', 'like', "%$searchWhere%")
                ->whereOr('country', 'like', "%$searchWhere%")
                ->whereOr('province', 'like', "%$searchWhere%")
                ->whereOr('city', 'like', "%$searchWhere%")
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->whereOr('id', '=', $searchWhere)
                ->whereOr('nickname', 'like', "%$searchWhere%")
                ->whereOr('country', 'like', "%$searchWhere%")
                ->whereOr('province', 'like', "%$searchWhere%")
                ->whereOr('city', 'like', "%$searchWhere%")
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            $this->success('查看粉丝', null, $result);
        }
        return $this->view->fetch();
    }

    //同步粉丝
    public function syncfans()
    {
        $wechatFans = new \app\admin\model\shopro\wechat\Fans;
        // 检测队列
        checkEnv('queue');

        try {
            //批量更新粉色关注状态为未关注
            $this->model->where('subscribe', 1)->update(['subscribe' => 0]);
            $wechat = new \addons\shopro\library\Wechat('wxOfficialAccount');
            $res = $wechat->asyncFans();
        } catch (\Exception $e) {  //如书写为（Exception $e）将无效
            if (strpos($e->getMessage(), 'ip')) {
                $this->error('请将当前IP地址加入公众号后台IP白名单');
            }
            $this->error($e->getMessage());
        }
        $this->success($res['msg']);
    }

    public function user()
    {
        $get = $this->request->get();
        $user = \think\Db::name('shopro_user_oauth')->where([
            'platform' => 'wxOfficialAccount',
            'openid' => $get['openid'],
        ])->find();
        if ($user) {
            $user_id = $user['user_id'];
            $this->success('找到用户', null, $user_id);
        } else {
            $this->error('暂未成为商城用户');
        }
    }

}
