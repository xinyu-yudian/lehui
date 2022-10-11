<?php

namespace app\admin\controller\shopro;

use app\common\controller\Backend;
use think\Config;

/**
 * 控制台
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            $datetimerange = explode(' - ', $this->request->request('datetimerange'));
            $startTime = strtotime($datetimerange[0]);
            $endTime = strtotime($datetimerange[1]);
            $this->model = new \app\admin\model\shopro\order\Order;
            $where = [
                'createtime' => ['between', [$startTime, $endTime]]
            ];

            $list = $this->model
                ->where($where)
                ->with('item')
                ->order('id')
                ->select();

            $data = $this->getTotalData($list);

            // 商品列表
            $goodsList = \addons\shopro\model\Goods::limit(5)->order('sales', 'desc')->select();
            foreach ($goodsList as $key => $goods) {
                $result = \app\admin\model\shopro\order\OrderItem::field('sum(goods_num * goods_price) as sale_total_money')->where('goods_id', $goods['id'])
                            ->whereExists(function ($query) use ($goods) {
                                $order_table_name = $this->model->getQuery()->getTable();
                                $table_name = (new \app\admin\model\shopro\order\OrderItem())->getQuery()->getTable();

                                $query->table($order_table_name)->where('order_id=' . $order_table_name . '.id')
                                    ->where('status', '>', \app\admin\model\shopro\order\Order::STATUS_NOPAY);       // 已支付的订单
                            })->find();

                $goods['sale_total_money'] = $result['sale_total_money'] ? : 0;
            }
            $data['goodsList'] = $goodsList;

            extract($this->orderScale($list));

            $data['orderFinish'] = $orderFinish;
            $data['payedFinish'] = $payedFinish;

            $this->success('数据中心', '', $data);
        }

        return $this->view->fetch();
    }



    private function orderScale ($list) {
        $total = count($list);
        $total_money = array_sum(array_column($list, 'total_fee'));

        $data['orderFinish'] = [
            'order_scale' => 0,
            'order_user' => 0
        ];
        $data['payedFinish'] = [
            'payed_scale' => 0,
            'payed_money' => 0
        ];

        // 支付单数
        $payed_num = 0;
        // 支付金额
        $payed_money = 0;
        // 支付的用户 id
        $payed_user_ids = [];

        foreach ($list as $key => $order) {
            if ($order['status'] > 0) {
                $payed_num++;
                $payed_money = bcadd($payed_money, $order['total_fee'], 2);
                $payed_user_ids[] = $order['user_id'];
            }
        }

        $orderFinish = [
            'order_scale' => $total ? round(($payed_num / $total), 2) : 0,
            'order_payed' => $payed_num,
        ];

        $payedFinish = [
            'payed_scale' => $total_money ? round(($payed_money / $total_money), 2) : 0,
            'payed_money' => round($payed_money, 2)
        ];

        return compact("orderFinish", "payedFinish");
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
        //支付人数
        $data['orderNum'] = count($list);
        $data['orderArr'] = [];
        //售后维权
        $data['aftersaleNum'] = 0;
        $data['aftersaleArr'] = [];
        //退款订单
        $data['refundNum'] = 0;
        $data['refundArr'] = [];
        //所有下单金额
        $data['totalAmount'] = 0;
        $data['tranPeople'] = [];


        $data['wechatPay'] = 0;
        $data['alipayPay'] = 0;
        $data['walletPay'] = 0;
        $data['allTypePay'] = 0;

        foreach ($list as $key => $order) {
            $data['orderArr'][] = [
                'counter' => 1,
                'createtime' => $order['createtime'] * 1000,
                'user_id' => $order['user_id']
            ];

            $data['totalAmount'] = bcadd($data['totalAmount'], $order['pay_fee'], 2);      // 这里可能要使用 total_fee

            if ($order['status'] > 0) {
                $data['payOrderNum']++;

                $data['payOrderArr'][] = [
                    'counter' => 1,
                    'createtime' => $order['createtime'] * 1000,
                    'user_id' => $order['user_id']
                ];

                $data['payAmountNum'] = bcadd($data['payAmountNum'], $order['pay_fee'], 2);

                $data['payAmountArr'][] = [
                    'counter' => $order['pay_fee'],
                    'createtime' => $order['createtime'] * 1000,
                ];

                $data['tranPeople']++;

                $flagnoSent = false;
                $flagaftersale = false;
                $flagrefund = false;

                foreach ($order['item'] as $k => $item) {
                    if (!$flagnoSent && $item['dispatch_status'] == 0 && $item['refund_status'] == 0) {
                        $data['noSentNum']++;
                        $data['noSentArr'][] = [
                            'counter' => 1,
                            'createtime' => $order['createtime'] * 1000,
                        ];

                        $flagnoSent = true;
                    }

                    if (!$flagaftersale && $item['aftersale_status'] > 0) {
                        $data['aftersaleNum']++;
                        $data['aftersaleArr'][] = [
                            'counter' => 1,
                            'createtime' => $order['createtime'] * 1000,
                        ];
                        $flagaftersale = true;
                    }

                    if (!$flagrefund && $item['refund_status'] > 0) {
                        $data['refundNum']++;
                        $data['refundArr'][] = [
                            'counter' => 1,
                            'createtime' => $order['createtime'] * 1000,
                        ];
                        $flagrefund = true;
                    }
                }

                $data['allTypePay']++;
                if ($order['pay_type'] == 'wechat') {
                    $data['wechatPay']++;
                }
                if ($order['pay_type'] == 'alipay') {
                    $data['alipayPay']++;
                }
                if ($order['pay_type'] == 'wallet') {
                    $data['walletPay']++;
                }
            }
        }

        return $data;
    }
}
