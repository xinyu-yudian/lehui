<?php

namespace app\admin\controller\shopro;

use app\admin\model\shopro\user\User;
use app\common\controller\Backend;
use think\Db;
use addons\shopro\library\Export;
use addons\shopro\model\UserWalletApply as WithDraw;

/**
 * 用户提现
 *
 * @icon fa fa-circle-o
 */
class UserWalletApply extends Base
{
    protected $noNeedRight = ['getType'];

    /**
     * UserWalletApply模型对象
     * @var \app\admin\model\shopro\UserWalletApply
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\UserWalletApply;
        $this->view->assign("getTypeList", $this->model->getApplyTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->assignconfig('typeList', $this->model->getApplyTypeList());
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

            $nobuildfields = ['user_nickname', 'user_mobile'];
            list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

            $total = $this->buildSearch()
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->buildSearch()
                ->with('user')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }


    // 提现导出
    public function export()
    {
        $nobuildfields = ['user_nickname', 'user_mobile'];
        list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

        $expCellName = [
            'id' => 'Id',
            'apply_sn' => '提现单号',
            'user_nickname' => '用户姓名',
            'user_phone' => '手机号',
            'money' => '提现金额',
            'actual_money' => '实际到账',
            'charge_money' => '手续费',
            'service_fee' => '手续费率',
            'apply_type' => '提现方式',
            'apply_info' => '打款信息',
            'status' => '状态',
            'createtime' => '申请时间',
            'updatetime' => '处理时间',
        ];

        $export = new Export();
        $spreadsheet = null;
        $sheet = null;

        $total = $this->buildSearch()->where($where)->order($sort, $order)->count();
        $page_size = 2000;
        $total_page = intval(ceil($total / $page_size));
        $newList = [];
        $money_total = 0;       // 提现总金额
        $actual_money_total = 0;       // 到账总金额
        $charge_money_total = 0;       // 手续费总金额

        if ($total == 0) {
            $this->error('导出数据为空');
        }

        for ($i = 0; $i < $total_page; $i++) {
            $page = $i + 1;
            $is_last_page = ($page == $total_page) ? true : false;

            $list = $this->buildSearch()
                ->with('user')
                ->where($where)
                ->order($sort, $order)
                ->limit(($i * $page_size), $page_size)
                ->select();

            $list = collection($list)->toArray();

            $newList = [];
            foreach ($list as $key => $apply) {
                $applyinfo = '';
                foreach ($apply['apply_info_text'] as $name => $info) {
                    $applyinfo .= $name . '：' . $info . "  \n";
                }

                $newList[] = [
                    'id' => $apply['id'],
                    'apply_sn' => $apply['apply_sn'],
                    'user_nickname' => $apply['user'] ? (strpos($apply['user']['nickname'], '=') === 0 ? ' ' . $apply['user']['nickname'] : $apply['user']['nickname']) : '',
                    'user_phone' => $apply['user'] ? $apply['user']['mobile'] . ' ' : '',
                    'money' => $apply['money'] . '元',
                    'actual_money' => $apply['actual_money'] . '元',
                    'charge_money' => $apply['charge_money'] . '元',
                    'service_fee' => $apply['service_fee'],
                    'apply_type' => $apply['apply_type_text'],
                    'apply_info' => $applyinfo,
                    'status' => $apply['status_text'],
                    'createtime' => date('Y-m-d H:i:s', $apply['createtime']),
                    'updatetime' => date('Y-m-d H:i:s', $apply['updatetime']),
                ];

                $money_total += $apply['money'];       // 提现总金额
                $actual_money_total += $apply['actual_money'];       // 到账总金额
                $charge_money_total += $apply['charge_money'];       // 手续费总金额
            }

            if ($is_last_page) {
                $newList[] = [
                    'id' => "提现申请总数：" . $total . "；提现总金额：￥" . $money_total . "；到账总金额：￥" . $actual_money_total . "；手续费总金额：￥" . $charge_money_total . "；"
                ];
            }

            $export->exportExcel('提现列表-' . date('Y-m-d H:i:s'), $expCellName, $newList, $spreadsheet, $sheet, [
                'page' => $page,
                'page_size' => $page_size,
                'is_last_page' => $is_last_page
            ]);
        }
    }


    // 获取要查询的提现类型
    public function getType()
    {
        $apply_type = $this->model->getApplyTypeList();
        $status = $this->model->getStatusList();

        $result = [
            'apply_type' => $apply_type,
            'status' => $status,
        ];

        $data = [];
        foreach ($result as $key => $list) {
            $data[$key][] = ['name' => '全部', 'type' => 'all'];

            foreach ($list as $k => $v) {
                $data[$key][] = [
                    'name' => $v,
                    'type' => $k
                ];
            }
        }

        return $this->success('操作成功', null, $data);
    }

    public function handle($ids)
    {
        $successCount = 0;
        $failedCount = 0;
        $ids = explode(',', $ids);
        $applyList = $this->model->where('id', 'in', $ids)->select();
        if (!$applyList) {
            $this->error('未找到该提现申请');
        }
        $operate = $this->request->post('operate');
        foreach ($applyList as $apply) {
            Db::startTrans();
            try {
                switch ($operate) {
                    case '1':
                        WithDraw::handleAgree($apply);
                        $apply->status === 1 ? $successCount++ : $failedCount++;
                        break;
                    case '2':
                        WithDraw::handleWithdraw($apply);
                        $apply->status === 2 ? $successCount++ : $failedCount++;
                        break;
                    case '3':
                        WithDraw::handleAgree($apply);
                        WithDraw::handleWithdraw($apply);
                        $apply->status === 2 ? $successCount++ : $failedCount++;
                        break;
                    case '-1':
                        $rejectInfo = $this->request->post('rejectInfo');
                        if (!$rejectInfo) {
                            throw \Exception('请输入拒绝原因');
                        }
                        WithDraw::handleReject($apply, $rejectInfo);
                        $apply->status === -1 ? $successCount++ : $failedCount++;;
                        break;
                }
                // 提现结果通知
                $user = \addons\shopro\model\User::get($apply->user_id);
                $user && $user->notify(
                    new \addons\shopro\notifications\Wallet([
                        'apply' => $apply,
                        'event' => 'wallet_apply'
                    ])
                );
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                WithDraw::handleLog($apply, '失败: ' . $e->getMessage());
                $failedCount++;
                $lastErrorMessage = $e->getMessage();
            }
        }
        if (count($ids) === 1) {
            if ($successCount) $this->success('操作成功');
            if ($failedCount) $this->error($lastErrorMessage);
        } else {
            $this->success('成功: ' . $successCount . '笔' . ' | 失败: ' . $failedCount . '笔');
        }
    }

    public function log($id)
    {
        $apply = $this->model->get($id);
        if (!$apply) {
            $this->error('未找到该提现日志');
        }
        $applyLog = $apply->log;
        if ($applyLog) {
            foreach ($applyLog as &$log) {
                $log['oper'] = \addons\shopro\library\Oper::get($log['oper_type'], $log['oper_id']);
            }
        }
        $this->success('提现日志', null, $applyLog);
    }

    /**
     * 提现搜索
     *
     * @return object
     */
    public function buildSearch()
    {
        $filter = $this->request->get("filter", '');
        $filter = (array)json_decode($filter, true);
        $filter = $filter ? $filter : [];

        $user_nickname = isset($filter['user_nickname']) ? $filter['user_nickname'] : '';
        $user_mobile = isset($filter['user_mobile']) ? $filter['user_mobile'] : '';

        // 当前表名
        $tableName = $this->model->getQuery()->getTable();

        $applys = $this->model;

        // 购买人查询
        if ($user_nickname || $user_mobile) {
            $applys = $applys->whereExists(function ($query) use ($user_nickname, $user_mobile, $tableName) {
                $userTableName = (new \app\admin\model\User())->getQuery()->getTable();
                $query = $query->table($userTableName)->where($userTableName . '.id=' . $tableName . '.user_id');

                if ($user_nickname) {
                    $query = $query->where('nickname', 'like', "%{$user_nickname}%");
                }

                if ($user_mobile) {
                    $query = $query->where('mobile', 'like', "%{$user_mobile}%");
                }

                return $query;
            });
        }

        return $applys;
    }
}
