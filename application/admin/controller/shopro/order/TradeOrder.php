<?php

namespace app\admin\controller\shopro\order;

use addons\shopro\library\Export;
use addons\shopro\model\Config as ModelConfig;
use app\admin\model\shopro\order\OrderExpress;
use app\admin\model\shopro\order\Verify;
use think\Db;
use think\Config;
use app\common\controller\Backend;
use app\admin\controller\shopro\Base;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;
use app\admin\model\shopro\order\OrderItem;

/**
 * 交易订单管理
 *
 * @icon fa fa-circle-o
 */
class TradeOrder extends Base
{

    /**
     * Order模型对象
     * @var \app\admin\model\shopro\order\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();

        $this->model = new \app\admin\model\shopro\order\TradeOrder;
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看列表
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $nobuildfields = ['status', 'nickname', 'user_phone'];
            list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

            $total = $this->buildSearchOrder()
                ->where($where)
                ->removeOption('soft_delete')
                ->order($sort, $order)
                ->count();

            $list = $this->buildSearchOrder()
                ->where($where)
                ->with(['user'])
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }



    // 订单详情
    public function detail($id)
    {
        if ($this->request->isAjax()) {
            $row = $this->model->withTrashed()->with(['user'])->where('id', $id)->find();
            if (!$row) {
                $this->error(__('No Results were found'));
            }

            return $this->success('获取成功', null,  $row);
        }

        $this->assignconfig('id', $id);
        return $this->view->fetch();
    }



    // 构建查询条件
    private function buildSearchOrder()
    {
        $filter = $this->request->get("filter", '');
        $filter = (array)json_decode($filter, true);
        $filter = $filter ? $filter : [];

        $status = isset($filter['status']) ? $filter['status'] : 'all';
        $nickname = isset($filter['nickname']) ? $filter['nickname'] : '';
        $mobile = isset($filter['user_phone']) ? $filter['user_phone'] : '';

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $orders = $this->model->withTrashed();

        if ($nickname || $mobile) {
            $orders = $orders->whereExists(function ($query) use ($nickname, $mobile, $tableName) {
                $userTableName = (new \app\admin\model\User())->getQuery()->getTable();

                $query = $query->table($userTableName)->where($userTableName . '.id=' . $tableName . 'user_id');

                if ($nickname) {
                    $query = $query->where('nickname', 'like', "%{$nickname}%");
                }

                if ($mobile) {
                    $query = $query->where('mobile', 'like', "%{$mobile}%");
                }

                return $query;
            });
        }

        // 订单状态
        if ($status != 'all' && in_array($status, ['invalid', 'cancel', 'nopay', 'payed', 'finish'])) {
            $orders = $orders->{$status}();
        }

        return $orders;
    }
}
