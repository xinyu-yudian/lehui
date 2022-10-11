<?php

namespace app\admin\controller\shopro\store;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;


/**
 * 门店统计
 *
 * @icon fa fa-circle-o
 */
class Dashboard extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();

        $this->model = new \app\admin\model\shopro\order\Order;
    }

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $params = $this->request->request();
            $datetimerange = $params['datetimerange'] ? explode(' - ', $params['datetimerange']) : [];
            $startTime = strtotime($datetimerange[0]);
            $endTime = strtotime($datetimerange[1]);
            $where = [
                'createtime' => ['between', [$startTime, $endTime]]
            ];
            $store_id = isset($params['store_id']) ? $params['store_id'] : 0;

            $list = $this->buildSearch()
                ->with(['item' => function ($query) use ($store_id) {
                    if ($store_id) {
                        $query->where('store_id', $store_id);
                    } else {
                        $query->where('store_id', '<>', 0);
                    }
                }])
                ->where($where)
                ->order('id')
                ->select();

            $data = $this->getTotalData($list);

            $this->success('数据中心', null, $data);
        }

        return $this->view->fetch();
    }


    private function getTotalData($list) {
        // 支付订单
        $data['payOrderNum'] = 0;
        $data['payOrderArr'] = [];
        //支付金额	
        $data['payAmountNum'] = 0;
        $data['payAmountArr'] = [];
        // 代发货
        $data['noSentNum'] = 0;
        $data['noSentArr'] = [];
        //下单人数
        $data['downNum'] = 0;
        $data['downArr'] = [];
        //总订单数
        $data['orderNum'] = 0;
        $data['orderArr'] = [];
        //售后维权
        $data['aftersaleNum'] = 0;
        $data['aftersaleArr'] = [];
        //退款订单
        $data['refundNum'] = 0;
        $data['refundArr'] = [];
        // 待配送/待核销
        $data['offSentNum'] = 0;
        $data['offSentArr'] = [];

        $userIds = [];
        foreach ($list as $key => $order) {
            // 总订单数
            $data['orderNum']++;
            $data['orderArr'][] = [
                'counter' => 1,
                'createtime' => $order['createtime'] * 1000,
                'user_id' => $order['user_id']
            ];

            if (!in_array($order['user_id'], $userIds)) {
                $userIds[] = $order['user_id'];
                $data['downNum']++;
                $data['downArr'][] = [
                    'counter' => 1,
                    'createtime' => $order['createtime'] * 1000,
                    'user_id' => $order['user_id']
                ];
            }

            if ($order['status'] > 0) {
                // 支付订单
                $data['payOrderNum']++;
                $data['payOrderArr'][] = [
                    'counter' => 1,
                    'createtime' => $order['createtime'] * 1000,
                    'user_id' => $order['user_id']
                ];

                // 支付金额
                $data['payAmountNum'] = bcadd($data['payAmountNum'], $order['pay_fee'], 2);
                $data['payAmountArr'][] = [
                    'counter' => $order['pay_fee'],
                    'createtime' => $order['createtime'] * 1000
                ];

                $flagnoSent = false;
                $flagoffSent = false;
                $flagaftersale = false;
                $flagrefund = false;
                foreach ($order['item'] as $k => $item) {
                    //待备货
                    if (!$flagnoSent && $item['dispatch_status'] == 0 && $item['refund_status'] <= 0) {
                        $data['noSentNum']++;
                        $data['noSentArr'][] = [
                            'counter' => 1,
                            'createtime' => $item['createtime'] * 1000
                        ];

                        $flagnoSent = true;
                    }

                    //待配送/待核销
                    if (!$flagoffSent && $item['dispatch_status'] == 1 && $item['refund_status'] <= 0) {
                        $data['offSentNum']++;
                        $data['offSentArr'][] = [
                            'counter' => 1,
                            'createtime' => $item['createtime'] * 1000
                        ];
                        $flagoffSent = true;
                    }

                    // 维权
                    if (!$flagaftersale && $item['aftersale_status'] > 0) {
                        $data['aftersaleNum']++;
                        $data['aftersaleArr'][] = [
                            'counter' => 1,
                            'createtime' => $item['createtime'] * 1000
                        ];

                        $flagaftersale = true;
                    }
                    // 退款
                    if (!$flagrefund && $item['refund_status'] > 0) {
                        $data['refundNum']++;
                        $data['refundArr'][] = [
                            'counter' => 1,
                            'createtime' => $item['createtime'] * 1000
                        ];

                        $flagrefund = true;
                    }
                }
            }

        }

        return $data;
    }


    public function buildSearch() 
    {
        $params = $this->request->request();
        $store_id = isset($params['store_id']) ? $params['store_id'] : 0;

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $orders = $this->model->withTrashed();
        
        $orders = $orders->whereExists(function ($query) use ($store_id, $tableName) {
            $itemTableName = (new \app\admin\model\shopro\order\OrderItem())->getQuery()->getTable();

            $query = $query->table($itemTableName)->where($itemTableName . '.order_id=' . $tableName . 'id');

            if ($store_id) {
                $query = $query->where('store_id', $store_id);
            } else {
                $query = $query->where('store_id', '<>', 0);
            }

            return $query;
        }); 

        return $orders;
    }
}
