<?php

namespace app\admin\controller\shopro\user;

use app\common\controller\Backend;
use think\Db;
use app\admin\model\shopro\user\Oauth;
use Exception;
use addons\shopro\exception\Exception as ShoproException;
use think\exception\PDOException;
use app\admin\model\shopro\user\WalletLog;


/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;

    /**
     * @var \app\admin\model\user\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\user\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $searchWhere = $this->request->request('searchWhere');
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with('group')
                ->where($where)
                ->whereOr('user.id', '=', $searchWhere)
                ->whereOr('nickname', 'like', "%$searchWhere%")
                ->whereOr('mobile', 'like', "%$searchWhere%")
                ->order($sort, $order)
                ->count();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->whereOr('user.id', '=', $searchWhere)
                ->whereOr('nickname', 'like', "%$searchWhere%")
                ->whereOr('mobile', 'like', "%$searchWhere%")
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => $v) {
                $list[$k]['share_nickname'] = '-';
                $list[$k]['share_mobile'] = '-';
                // 获取上级
                if($v['pid'] > 0){
                    $share_user = \app\admin\model\User::find($v['pid']);
                    if($share_user){
                        $list[$k]['share_nickname'] = $share_user['nickname'];
                        $list[$k]['share_mobile'] = $share_user['mobile'];
                    }
                }
                $v->hidden(['password', 'salt']);
                $v->third_platform = Oauth::all(['user_id' => $v->id]);
            }
            $result = array("total" => $total, "rows" => $list);

            $this->success('查看用户', null, $result);
        }
        return $this->view->fetch();
    }

    /**
     * 用户详情
     */
    public function profile($id)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error('未找到用户');
        }
        $row->hidden(['password', 'salt']);
        $row->third_platform = Oauth::all(['user_id' => $row->id]);
        if (checkEnv('commission', false)) {
            $row->parent_user = $this->model->get($row->parent_user_id);
        }
        if ($this->request->isAjax()) {
            $this->success('用户详情', null, $row);
        }
        $this->assignconfig('row', $row);
        $this->assignconfig('groupList', \app\admin\model\UserGroup::field('id,name,status')->select());
        return $this->view->fetch();
    }

    /**
     * 更新信息
     */
    public function update()
    {
        $params = $this->request->post('data');
        $params = json_decode($params, true);
        $user = $this->model->get($params['id']);
        if (!$user) {
            $this->error('未找到用户');
        }
        $result = Db::transaction(function () use ($user, $params) {

            try {
                if (!empty($params['password'])) {
                    $salt = \fast\Random::alnum();
                    $user->password = \app\common\library\Auth::instance()->getEncryptPassword($params['password'], $salt);
                    $user->salt = $salt;
                    $user->save();
                }
                $verification = $user->verification;
                if (!empty($params['mobile'])) {
                    $verification->mobile = 1;
                } else {
                    $verification->mobile = 0;
                }
                $user->verification = $verification;
                $user->save();

                return $user->validate('\app\admin\validate\shopro\user\User.update')->allowField('nickname,avatar,username,group_id,birthday,bio,mobile,email,level,gender,status')->save($params);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        });

        if ($result) {
            return $this->success('更新成功', null, $user);
        } else {
            return $this->error($user->getError());
        }
    }

    /**
     * 选择
     */
    public function select()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $searchWhere = $this->request->request('search');
            $total = $this->model
                ->where($where)
                ->whereOr('id', '=', $searchWhere)
                ->whereOr('nickname', 'like', "%$searchWhere%")
                ->whereOr('mobile', 'like', "%$searchWhere%")
                ->order($sort, $order)
                ->field('id, nickname, mobile, avatar')
                ->count();
            $list = $this->model
                ->where($where)
                ->whereOr('id', '=', $searchWhere)
                ->whereOr('nickname', 'like', "%$searchWhere%")
                ->whereOr('mobile', 'like', "%$searchWhere%")
                ->order($sort, $order)
                ->field('id, nickname, mobile, avatar')
                ->limit($offset, $limit)
                ->select();
            $result = array("total" => $total, "rows" => $list);

            $this->success('选择用户', null, $result);
        }
        return $this->view->fetch();
    }

    /**
     * 用户余额充值
     */
    public function money_recharge()
    {

        if ($this->request->isAjax()) {
            $params = $this->request->post();
            $user = $this->model->get($params['user_id']);
            $params['money'] = $params['money'];
            if ($params['money'] > 0) {
                $type = 'admin_recharge';
            } elseif ($params['money'] < 0) {
                $type = 'admin_deduct';
            } else {
                $this->error('请输入正确的金额');
            }
            $result = Db::transaction(function () use ($params, $user, $type) {
                return \addons\shopro\model\User::money($params['money'], $user->id, $type, 0, $params['remarks']);
            });
            if ($result) {
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }
        return $this->view->fetch();
    }

    /**
     * 用户积分充值
     */
    public function score_recharge()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->post();
            $user = $this->model->get($params['user_id']);
            $params['score'] = intval($params['score']);
            if ($params['score'] > 0) {
                $type = 'admin_recharge';
            } elseif ($params['score'] < 0) {
                $type = 'admin_deduct';
            } else {
                $this->error('请输入正确的数量');
            }
            $result = Db::transaction(function () use ($params, $user, $type) {
                try {
                    return \addons\shopro\model\User::score($params['score'], $user->id, $type, 0, $params['remarks']);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
            });
            if ($result) {
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        }
        return $this->view->fetch();
    }

    /**
     * 余额明细
     */
    public function money_log($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {
            $model = new WalletLog();
            $data = $model->where(['user_id' => $user_id, 'wallet_type' => 'money'])->order('id desc')->paginate($limit);
            $this->success('余额明细', null, $data);
        }
    }

    /**
     * 积分明细
     */
    public function score_log($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {
            $model = new WalletLog();
            $data = $model->where(['user_id' => $user_id, 'wallet_type' => 'score'])->order('id desc')->paginate($limit);
            $this->success('积分明细', null, $data);
        }
    }

    /**
     * 订单记录
     */
    public function order_log($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {
            $this->loadlang('shopro/order/order');
            $model = new \app\admin\model\shopro\order\Order;
            $data = $model->where('user_id', $user_id)->order('id desc')->paginate($limit);
            $this->success('订单记录', null, $data);
        }
    }

    /**
     * 登录记录
     */
    public function login_log($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {
        }
    }

    /**
     * 分享记录
     */
    public function share_log($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {
            $this->loadlang('shopro/share');
            $model = new \app\admin\model\shopro\Share;
            $data = $model->where('share_id', $user_id)->order('id desc')->with([
                'user' => function ($query) {
                    return $query->withField('id,nickname,avatar');
                }
            ])->paginate($limit);
            foreach ($data as &$v) {
                if ($v['type'] === 'goods') {
                    $v['goods'] = \app\admin\model\shopro\goods\Goods::where('id', $v['type_id'])->field('id, image, title')->find();
                }
                if ($v['type'] === 'groupon') {
                    $v['groupon'] = \app\admin\model\shopro\activity\Groupon::get($v['type_id']);
                }
            }
            $this->success('分享记录', null, $data);
        }
    }

    /**
     * 收藏商品
     */
    public function goods_favorite($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {

            $model = new \app\admin\model\shopro\user\Favorite;
            $data = $model->where('user_id', $user_id)->order('id desc')->with([
                'goods' => function ($query) {
                    return $query->withField('id,title,image');
                }
            ])->paginate($limit);
            $this->success('商品收藏', null, $data);
        }
    }

    /**
     * 浏览足迹
     */
    public function goods_view($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {

            $model = new \app\admin\model\shopro\user\View;
            $data = $model->where('user_id', $user_id)->order('id desc')->with([
                'goods' => function ($query) {
                    return $query->withField('id,title,image');
                }
            ])->paginate($limit);
            $this->success('商品收藏', null, $data);
        }
    }

    /**
     * 优惠券
     */
    public function coupon_log($user_id, $limit = 10)
    {
        if ($this->request->isAjax()) {
            $model = new \app\admin\model\shopro\user\Coupon;
            $data = $model->where('user_id', $user_id)->order('id desc')->with(['coupons' => function ($query) {
                return $query->withField('id,name,amount');
            }])->paginate($limit);
            $this->success('优惠券', null, $data);
        }
    }


    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $pk = $this->model->getPk();
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    // 删除这个用户关联的 shopro_user_oauth 记录
                    Oauth::where('user_id', $v->id)->delete();

                    // 删除用户
                    $count += $v->delete();
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
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 更换上级推荐人
     */
    public function changeParentUser($id)
    {
        $user = $this->model->get($id);
        $value = $this->request->post('value');

        if (!$user) {
            $this->error('未找到用户');
        }
        $agent = new \addons\shopro\library\commission\Agent($id);
        if ($agent->agent) {
            $this->error('请前往分销中心操作该用户');
        }
        try {
            if ($user->parent_user_id == $value) {
                throw \Exception('请勿重复选择');
            }
            if ($user->id == $value) {
                throw \Exception('不能绑定本人');
            }
            if ($value != 0) {
                $parentAgent = new \addons\shopro\library\commission\Agent($value);
                if (!$parentAgent || !$parentAgent->agent) {
                    throw \Exception('未找到该分销商');
                }
            }
            if (!$this->checkChangeParentAgent($user->id, $value)) {
                throw \Exception('不能绑定该分销商');
            }
            $runUpgradeLastAgentId = $user->parent_user_id;
            $user->parent_user_id = $value;
            $user->save();
            if (!empty($runUpgradeLastAgentId)) {
                $agent->asyncAgentUpgrade($runUpgradeLastAgentId);
            }
            if (!empty($user->parent_user_id)) {
                $agent->asyncAgentUpgrade($user->parent_user_id);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('绑定成功');
    }

    // 递归往上找推荐人，防止出现推荐闭环
    private function checkChangeParentAgent($userId, $parentAgentId)
    {
        if ($userId === $parentAgentId) {
            return false;
        }
        if ($parentAgentId == 0) {
            return true;
        }

        $parentAgent = \app\admin\model\shopro\commission\Agent::get($parentAgentId);
        if ($parentAgent) {
            if ($parentAgent->parent_agent_id === 0) {
                return true;
            } else {
                return $this->checkChangeParentAgent($userId, $parentAgent->parent_agent_id);
            }
        }

        return false;
    }
}
