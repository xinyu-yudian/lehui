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
use PrintOrderFei\PrintOrderFei;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Base
{
    protected $noNeedRight = ['getType', 'getExpress'];

    /**
     * Order模型对象
     * @var \app\admin\model\shopro\order\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();

        // 手动加载语言包
        $this->loadlang('shopro/order/order_item');
        $this->loadlang('shopro/dispatch/dispatch');
        $this->loadlang('shopro/goods/goods');

        $this->model = new \app\admin\model\shopro\order\Order;
        $this->storeModel = new \app\admin\model\shopro\store\Store;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("payTypeList", $this->model->getPayTypeList());
        $this->view->assign("platformList", $this->model->getPlatformList());
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

            $nobuildfields = ['status', 'aftersale_sn', 'dispatch_type', 'goods_type', 'nickname', 'user_phone', 'goods_title', 'store_id'];
            list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

            $total = $this->buildSearchOrder()
                ->where($where)
                ->removeOption('soft_delete')
                ->order($sort, $order)
                ->count();

            $list = $this->buildSearchOrder()
                ->where($where)
                ->with(['user', 'item'])
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $items = [];
            foreach ($list as $key => $od) {
                // 处理 未支付订单 的 订单 item status_code 状态
                $list[$key] = $this->model->setOrderItemStatusByOrder($od);

                $items = array_merge($items, $od['item']);
            }

            $store_ids = array_unique(array_column($items, 'store_id'));
            $stores = $this->storeModel->where('id', 'in', $store_ids)->select();
            $stores = collection($stores)->toArray();
            $stores = array_column($stores, null, 'id');

            foreach ($list as $key => $od) {
                foreach ($od['item'] as $k => $it) {
                    $list[$key]['item'][$k]['store'] = $stores[$it['store_id']] ?? null;
                    if($od['is_cook'] == 1 && $it['ext'] != []){
                        $temp = json_decode($it['ext'],true);
                        $list[$key]['dispatch_date'] = isset($temp['dispatch_date'])?$temp['dispatch_date']:'';
                        $list[$key]['dispatch_phone'] = isset($temp['dispatch_phone'])?$temp['dispatch_phone']:'';
                    }
                }
//                if($od['is_cook'] == 1 && $od['item'][0]['ext'] != []){
//                    $temp = json_decode($od['item'][0]['ext'],true);
//                    $list[$key]['dispatch_date'] = isset($temp['dispatch_date'])?$temp['dispatch_date']:'';
//                    $list[$key]['dispatch_phone'] = isset($temp['dispatch_phone'])?$temp['dispatch_phone']:'';
//                }
            }

            $result = array("total" => $total, "rows" => $list);

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }


    // 订单导出
    public function export()
    {
        $nobuildfields = ['status', 'aftersale_sn', 'dispatch_type', 'goods_type', 'nickname', 'user_phone', 'goods_title', 'store_id'];
        list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

        $expCellName = [
            'order_id' => 'Id',
            'order_sn' => '订单号',
            'type_text' => '订单类型',
            'status_text' => '订单状态',
            'pay_type_text' => '支付类型',
            'paytime_text' => '支付时间',
            'platform_text' => '交易平台',
            'user_nickname' => '用户姓名',
            'user_phone' => '手机号',
            'store_info' => '门店信息',
            'total_amount' => '订单总金额',
            'discount_fee' => '优惠金额',
            'pay_fee' => '实际支付金额',
            'score_fee' => '积分支付数量',
            'consignee_info' => '收货信息',
            'remark' => '用户备注',
            'activity_type_text' => '营销类型',
            'goods_title' => '商品名称',
            'goods_original_price' => '商品原价',
            'goods_price' => '商品价格',
            'goods_sku_text' => '商品规格',
            'goods_num' => '购买数量',
            'dispatch_status_text' => '发货状态',
            'dispatch_fee' => '发货费用',
            'dispatch_type_text' => '发货方式',
            'aftersale_refund' => '售后/退款',
            'comment_status_text' => '评价状态',
            'refund_fee' => '退款金额',
            'refund_msg' => '退款原因',
            'express_name' => '快递公司',
            'express_no' => '快递单号',
        ];

        $export = new Export();
        $spreadsheet = null;
        $sheet = null;

        $total = $this->buildSearchOrder()->where($where)->order($sort, $order)->count();
        $current_total = 0;     // 当前已循环条数
        $page_size = 2000;
        $total_page = intval(ceil($total / $page_size));
        $newList = [];
        $total_amount = 0;      // 订单总金额
        $discount_fee = 0;      // 优惠总金额
        $pay_fee = 0;      // 实际支付总金额
        $score_fee = 0;      // 支付总积分

        if ($total == 0) {
            $this->error('导出数据为空');
        }

        for ($i = 0; $i < $total_page; $i++) {
            $page = $i + 1;
            $is_last_page = ($page == $total_page) ? true : false;

            $list = $this->buildSearchOrder()
                ->where($where)
                ->with(['user', 'item'])
                ->order($sort, $order)
                ->limit(($i * $page_size), $page_size)
                ->select();

            $list = collection($list)->toArray();
            $items = [];
            foreach ($list as $key => $od) {
                // 处理 未支付订单 的 订单 item status_code 状态
                $list[$key] = $this->model->setOrderItemStatusByOrder($od);

                $items = array_merge($items, $od['item']);
            }
            $store_ids = array_unique(array_column($items, 'store_id'));
            $stores = $this->storeModel->where('id', 'in', $store_ids)->select();
            $stores = collection($stores)->toArray();
            $stores = array_column($stores, null, 'id');

            foreach ($list as $key => $od) {
                foreach ($od['item'] as $k => $it) {
                    $list[$key]['item'][$k]['store'] = $stores[$it['store_id']] ?? null;
                }
            }


            $newList = [];
            foreach ($list as $key => $ord) {
                $data = [
                    'order_id' => $ord['id'],
                    'order_sn' => $ord['order_sn'],
                    'type_text' => $ord['type_text'],
                    'status_text' => $ord['status_text'],
                    'pay_type_text' => $ord['pay_type_text'],
                    'paytime_text' => $ord['paytime_text'],
                    'platform_text' => $ord['platform_text'],
                    'user_nickname' => $ord['user'] ? (strpos($ord['user']['nickname'], '=') === 0 ? ' ' . $ord['user']['nickname'] : $ord['user']['nickname']) : '',
                    'user_phone' => $ord['user'] ? $ord['user']['mobile'] . ' ' : '',
                    'total_amount' => $ord['total_amount'],
                    'discount_fee' => $ord['discount_fee'],
                    'pay_fee' => $ord['pay_fee'],
                    'score_fee' => $ord['score_fee'],
                    'consignee_info' => ($ord['consignee'] ? ($ord['consignee'] . ':' . $ord['phone'] . '-') : '') . ($ord['province_name'] . '-' . $ord['city_name'] . '-' . $ord['area_name']) . ' ' . $ord['address'],
                    'remark' => $ord['remark']
                ];
                foreach ($ord['item'] as $k => $item) {
                    $itemData = [
                        'store_info' => $item['store'] ? $item['store']['name'] : '',
                        'activity_type_text' => $item['activity_type_text'],
                        'goods_title' => strpos($item['goods_title'], '=') === 0 ? ' ' . $item['goods_title'] : $item['goods_title'],
                        'goods_original_price' => $item['goods_original_price'],
                        'goods_price' => $item['goods_price'],
                        'goods_sku_text' => $item['goods_sku_text'],
                        'goods_num' => $item['goods_num'],
                        'dispatch_status_text' => $item['dispatch_status_text'],
                        'dispatch_fee' => $item['dispatch_fee'],
                        'dispatch_type_text' => $item['dispatch_type_text'],
                        'aftersale_refund' => $item['aftersale_status_text'] . '/' . $item['refund_status_text'],
                        'comment_status_text' => $item['comment_status_text'],
                        'refund_fee' => $item['refund_fee'],
                        'refund_msg' => $item['refund_msg'],
                        'express_name' => $item['express_name'],
                        'express_no' => $item['express_no'],
                    ];

                    $newList[] = array_merge($data, $itemData);
                }

                $total_amount += $ord['total_amount'];      // 订单总金额
                $discount_fee += $ord['discount_fee'];      // 优惠总金额
                $pay_fee += $ord['pay_fee'];      // 实际支付总金额
                $score_fee += $ord['score_fee'];      // 支付总积分
            }

            if ($is_last_page) {
                $newList[] = [
                    'order_id' => "订单总数：" . $total . "；订单总金额：￥" . $total_amount . "；优惠总金额：￥" . $discount_fee . "；实际支付总金额：￥" . $pay_fee . "；支付总积分：" . $score_fee
                ];
            }

            $current_total += count($newList);     // 当前循环总条数

            $export->exportExcel('订单列表-' . date('Y-m-d H:i:s'), $expCellName, $newList, $spreadsheet, $sheet, [
                'page' => $page,
                'page_size' => $page_size,      // 如果传了 current_total 则 page_size 就不用了
                'current_total' => $current_total,      // page_size 是 order 的，但是 newList 其实是 order_item 的
                'is_last_page' => $is_last_page
            ]);
        }
    }



    // 导出发货单
    public function exportDelivery()
    {
        $nobuildfields = ['status', 'aftersale_sn', 'dispatch_type', 'goods_type', 'nickname', 'user_phone', 'goods_title'];
        list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

        $expCellName = [
            'order_id' => 'Id',
            'order_sn' => '订单号',
            'order_item_id' => '子订单Id',
            'type_text' => '订单类型',
            'consignee_info' => '收货信息',
            'remark' => '用户备注',
            'goods_title' => '商品名称',
            'goods_sku_text' => '商品规格',
            'goods_num' => '购买数量',
            'dispatch_fee' => '发货费用',
            'dispatch_type_text' => '发货方式',
            'aftersale_refund' => '售后/退款',
            'express_no' => '快递单号',
        ];

        $export = new Export();
        $spreadsheet = null;
        $sheet = null;

        $total = $this->buildSearchOrder()->where($where)->order($sort, $order)->count();
        $current_total = 0;     // 当前已循环条数
        $page_size = 2000;
        $total_page = intval(ceil($total / $page_size));
        $newList = [];
        $orderCount = 0;

        if ($total == 0) {
            $this->error('导出数据为空');
        }

        for ($i = 0; $i < $total_page; $i++) {
            $page = $i + 1;
            $is_last_page = ($page == $total_page) ? true : false;

            $list = $this->buildSearchOrder()
                ->where($where)
                ->with(['user', 'item'])
                ->order($sort, $order)
                ->limit(($i * $page_size), $page_size)
                ->select();

            $list = collection($list)->toArray();
            foreach ($list as $key => $od) {
                // 处理 未支付订单 的 订单 item status_code 状态
                $list[$key] = $this->model->setOrderItemStatusByOrder($od);
            }

            $newList = [];

            foreach ($list as $key => $ord) {
                if ($ord['status_code'] == 'groupon_ing') {
                    // 拼团正在进行中，不发货
                    continue;
                }
                $data = [
                    'order_id' => $ord['id'],
                    'order_sn' => $ord['order_sn'],
                    'type_text' => $ord['type_text'],
                    'consignee_info' => ($ord['consignee'] ? ($ord['consignee'] . ':' . $ord['phone'] . '-') : '') . ($ord['province_name'] . '-' . $ord['city_name'] . '-' . $ord['area_name']) . ' ' . $ord['address'],
                    'remark' => $ord['remark']
                ];

                $existItem = false;           // 是否有符合发货的item
                foreach ($ord['item'] as $k => $item) {
                    // 未发货，并且未退款，并且未在申请售后中,并且是快递物流的
                    if (
                        $item['dispatch_status'] == OrderItem::DISPATCH_STATUS_NOSEND
                        && !in_array($item['refund_status'], [OrderItem::REFUND_STATUS_OK, OrderItem::REFUND_STATUS_FINISH])
                        && $item['aftersale_status'] != OrderItem::AFTERSALE_STATUS_AFTERING
                        && $item['dispatch_type'] == 'express'
                    ) {
                        $itemData = [
                            'order_item_id' => $item['id'],
                            'goods_title' => strpos($item['goods_title'], '=') === 0 ? ' ' . $item['goods_title'] : $item['goods_title'],
                            'goods_sku_text' => $item['goods_sku_text'],
                            'goods_num' => $item['goods_num'],
                            'dispatch_fee' => $item['dispatch_fee'],
                            'dispatch_type_text' => $item['dispatch_type_text'],
                            'aftersale_refund' => $item['aftersale_status_text'] . '/' . $item['refund_status_text'],
                            'express_no' => $item['express_no'],
                        ];

                        $newList[] = array_merge($data, $itemData);
                        $existItem = true;
                    }
                }

                if ($existItem) {
                    $orderCount++;
                }
            }

            if ($is_last_page) {
                $newList[] = [
                    'order_id' => "订单总数：" . $orderCount . ";(备注:同一订单中不同包裹请勿填写相同运单号)"
                ];
            }

            $current_total += count($newList);     // 当前循环总条数

            $export->exportExcel('发货单列表-' . date('Y-m-d H:i:s'), $expCellName, $newList, $spreadsheet, $sheet, [
                'page' => $page,
                'page_size' => $page_size,      // 如果传了 current_total 则 page_size 就不用了
                'current_total' => $current_total,      // page_size 是 order 的，但是 newList 其实是 order_item 的
                'is_last_page' => $is_last_page
            ]);
        }
    }



    // 获取要查询的订单类型
    public function getType()
    {
        $type = $this->model->getTypeList();
        $pay_type = $this->model->getPayTypeList();
        $platform = $this->model->getPlatformList();
        $dispatch_type = (new \app\admin\model\shopro\dispatch\Dispatch)->getTypeList();
        $activity_type = (new \app\admin\model\shopro\activity\Activity)->getTypeList();
        $goods_type = (new \app\admin\model\shopro\goods\Goods)->getTypeList();

        $result = [
            'type' => $type,
            'pay_type' => $pay_type,
            'platform' => $platform,
            'dispatch_type' => $dispatch_type,
            'activity_type' => $activity_type,
            'goods_type' => $goods_type
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


    // 订单详情
    public function detail($id)
    {
        if ($this->request->isAjax()) {
            $row = $this->model->withTrashed()->with(['user', 'item.store'])->where('id', $id)->find();
            if (!$row) {
                $this->error(__('No Results were found'));
            }

            $row->express = OrderExpress::with(['item' => function ($query) use ($id) {
                return $query->where('order_id', $id);
            }, 'log'])->where('order_id', $id)->select();

            // 处理未支付 item status_code
            $row = $this->model->setOrderItemStatusByOrder($row);

            // 返回 核销码
            $dispatchTypes = array_column($row['item'], 'dispatch_type');
            if (in_array('selfetch', $dispatchTypes)) {
                $verifies = Verify::where('order_id', $row['id'])->select();

                foreach ($row['item'] as &$item) {
                    $item['verifies'] = [];
                    if ($item['dispatch_type'] == 'selfetch') {
                        foreach ($verifies as $verify) {
                            if ($verify['order_item_id'] == $item['id']) {
                                $item['verifies'][] = $verify;
                            }
                        }
                    }
                }
            }

            return $this->success('获取成功', null, [
                'order' => $row,
                'item' => $row['item'],
                'express' => $row['express']
            ]);
        }

        $this->assignconfig('id', $id);
        return $this->view->fetch();
    }



    // API一键推单(默认快递鸟）
    public function deliverByApi($id, $item_ids = 'all', $express_id = 0)
    {
        list($order, $orderExpress, $item_lists) = $this->handleDeliveryBefore($id, $item_ids, $express_id);
        $type = 'kdniao';

        if ($type === 'kdniao') {
            try {
                $expressLib = new \addons\shopro\library\Express();
                $result = $expressLib->eorder($order, $item_lists);
                $express_code = $result['Order']['ShipperCode'];
                $express_no = $result['Order']['LogisticCode'];
                $express = \app\admin\model\shopro\Express::where('code', $express_code)->find();
                $express_name = $express ? $express['name'] : '';
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        list($orderExpress, $needSubscribe) = $this->handleDeliveryAfter($order, $item_lists, $orderExpress, $express_name, $express_code, $express_no);
        if ($needSubscribe) {
            $this->subscribeExpressInfo($express_code, $express_no, $orderExpress, $order);
        }
        return $this->success('发货成功', null);
    }

    // 批量发货(导入发货模板)
    public function deliverByUploadTemplate()
    {
        $file = $this->request->request('file');
        $express_name = $this->request->request('express_name');
        $express_code = $this->request->request('express_code');
        if (!$file) {
            $this->error(__('Parameter %s can not be empty', 'file'));
        }
        $filePath = ROOT_PATH . DS . 'public' . DS . $file;
        if (!is_file($filePath)) {
            $this->error(__('No results were found'));
        }
        //实例化reader
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if ($ext !== 'xlsx') {
            $this->error(__('Unknown data format'));
        }
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

        //导入文件首行类型,默认是注释,如果需要使用字段名称请使用name
        $importHeadType = isset($this->importHeadType) ? $this->importHeadType : 'comment';


        //加载文件
        $insert = [];
        try {
            if (!$PHPExcel = $reader->load($filePath)) {
                $this->error(__('Unknown data format'));
            }
            $currentSheet = $PHPExcel->getSheet(0);  //读取文件中的第一个工作表
            $allColumn = $currentSheet->getHighestDataColumn(); //取得最大的列号
            $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
            $maxColumnNumber = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($allColumn);
            if ($allRow <= 2) {
                $this->error('您的发货列表为空');
            }
            $orderList = [];
            for ($currentRow = 2; $currentRow <= $allRow - 1; $currentRow++) {
                $order = [];
                $order['id'] = $currentSheet->getCellByColumnAndRow(1, $currentRow)->getValue();
                $order['item_id'] = $currentSheet->getCellByColumnAndRow(3, $currentRow)->getValue();
                $order['express_no'] = $currentSheet->getCellByColumnAndRow(13, $currentRow)->getValue();
                $orderList[] = $order;
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
        if (!$orderList) {
            $this->error(__('No rows were updated'));
        }
        $expressArray = [];
        foreach ($orderList as $v) {
            $express_no = $v['express_no']; // 使用快递单号作为键名
            if(!$express_no) {
                $this->error('请填写正确的运单号');
            }
            if(isset($expressArray[$express_no])) {
                if($expressArray[$express_no]['order_id'] != $v['id']) {
                    $this->error('不同订单勿使用同一运单号');
                }
                $expressArray[$express_no]['item_ids'][] = $v['item_id'];
            }else {
                $expressArray[$express_no]['order_id'] = $v['id'];
                $expressArray[$express_no]['item_ids'][] = $v['item_id'];
            }
        }
        // 开始发货
        if(!$expressArray) $this->error('您的发货列表为空');
        foreach($expressArray as $k => $express) {
            $express_no = $k;
            $order_id = $express['order_id'];
            $item_ids = implode(',', $express['item_ids']);
            list($order, $orderExpress, $item_lists) = $this->handleDeliveryBefore($order_id, $item_ids, '');
            list($orderExpress, $needSubscribe) = $this->handleDeliveryAfter($order, $item_lists, $orderExpress, $express_name, $express_code, $express_no);
            if ($needSubscribe) {
                $this->subscribeExpressInfo($express_code, $express_no, $orderExpress, $order);
            }
        }
        $this->success('发货列表', null, $insert);
    }

    /**
     * 手动发货
     *
     * @param int $id     订单ID
     * @param string  $item_ids 订单中需发货的商品
     * @param int $express_id  发货包裹ID
     * @param string $express_name  快递公司名称
     * @param string $express_code  物流公司编号
     * @param string $express_no    运单号
     */
    public function deliverByInput($id, $item_ids = '', $express_id = null)
    {
        list($order, $orderExpress, $item_lists) = $this->handleDeliveryBefore($id, $item_ids, $express_id);
        $express_name = $this->request->post('express_name', '');
        $express_code = $this->request->post('express_code', '');
        $express_no = $this->request->post('express_no', '');
        list($orderExpress, $needSubscribe) = $this->handleDeliveryAfter($order, $item_lists, $orderExpress, $express_name, $express_code, $express_no);
        if ($needSubscribe) {
            $this->subscribeExpressInfo($express_code, $express_no, $orderExpress, $order);
        }

        return $this->success('发货成功');
    }

    /**
     * 处理发货前置检查流程
     *
     * @param int $id     订单ID
     * @param string  $item_ids 订单中需发货的商品
     * @param int $express_id  发货包裹ID
     * @param string $express_name  快递公司名称
     * @param string $express_code  物流公司编号
     * @param string $express_no    运单号
     */
    private function handleDeliveryBefore($id, $item_ids, $express_id)
    {
        $order = $this->model->payed()->where('id', $id)->find();

        if (!$order) {
            $this->error('订单不存在或未支付');
        }
        if ($item_ids == '') {
            $this->error('请选择待发货的商品');
        }
        if ($order->status_code === 'groupon_ing') {
            $this->error('该商品还未拼团成功,暂不能发货');
        }
        // 查询要发货的商品
        if ($item_ids == 'all') {   // 选中所有商品
            $where = [
                'order_id' => $id,
                'dispatch_type' => 'express'        // 必须是物流快递的商品
            ];
        } else {
            $where = [            // 选中分包裹商品
                'order_id' => $id,
                'id' => ['in', $item_ids],
                'dispatch_type' => 'express'
            ];
        }

        // 订单包裹
        $orderExpress = null;
        if ($express_id) {
            // 修改包裹
            $orderExpress = OrderExpress::where('id', $express_id)->find();
            if (!$orderExpress) {
                $this->error('包裹不存在');
            }
        }

        $dispatchWhere[] =  \app\admin\model\shopro\order\OrderItem::DISPATCH_STATUS_NOSEND;

        if ($express_id) {
            // 可以修改已发货的商品
            $dispatchWhere[] = \app\admin\model\shopro\order\OrderItem::DISPATCH_STATUS_SENDED;
        }
        $where['dispatch_status'] = ['in', $dispatchWhere];

        $item_lists = \app\admin\model\shopro\order\OrderItem::where($where)->select();

        if (!$item_lists) {
            $this->error('没有物流发货的商品');
        }
        return [$order, $orderExpress, $item_lists];
    }

    /**
     * 处理发货后置流程
     *
     * @param object $order     订单
     * @param array  $item_lists 订单中待发货的商品
     * @param object $orderExpress  发货包裹
     * @param string $express_name  快递公司名称
     * @param string $express_code  物流公司编号
     * @param string $express_no    运单号
     */
    private function handleDeliveryAfter($order, $item_lists, $orderExpress, $express_name, $express_code, $express_no)
    {
        if (!$express_name || !$express_code || !$express_no) {
            $this->error('请填写完整发货信息');
        }
        $needSubscribe = true;
        if ($orderExpress && $orderExpress->express_no == $express_no && $orderExpress->express_code == $express_code) {
            // 没有编辑快递信息，不需要重新订阅快递
            $needSubscribe = false;
        }
        $orderExpress =  Db::transaction(function () use ($order, $item_lists, $orderExpress, $express_name, $express_code, $express_no) {
            foreach ($item_lists as $key => $item) {
                $order->sendItem($order, $item, [
                    "express_name" => $express_name,
                    "express_code" => $express_code,
                    "express_no" => $express_no,
                    "oper" => $this->auth->getUserInfo(),
                    "oper_type" => 'admin',
                ]);
            }

            if (!$orderExpress) {
                // 添加包裹
                $orderExpress = new OrderExpress();
                $orderExpress->user_id = $order->user_id;
                $orderExpress->order_id = $order->id;
            } else {
                // 查询选择的包裹中未被选中的商品，改为未发货
                \app\admin\model\shopro\order\OrderItem::where('order_id', $order['id'])
                    ->where('id', 'not in', array_column($item_lists, 'id'))
                    ->where('express_no', $orderExpress->express_no)
                    ->where('express_code', $orderExpress->express_code)->update([
                        'express_name' => null,
                        'express_code' => null,
                        'express_no' => null,
                        'dispatch_status' => \app\admin\model\shopro\order\OrderItem::DISPATCH_STATUS_NOSEND
                    ]);
            }

            $orderExpress->express_name = $express_name;
            $orderExpress->express_code = $express_code;
            $orderExpress->express_no = $express_no;
            $orderExpress->save();

            // 查询已经没有商品的包裹，并且删除
            OrderExpress::whereNotExists(function ($query) use ($order) {
                $order_table_name = (new OrderExpress())->getQuery()->getTable();
                $table_name = (new \app\admin\model\shopro\order\OrderItem())->getQuery()->getTable();
                $query->table($table_name)->where($table_name . '.express_no=' . $order_table_name . '.express_no')
                    ->where($table_name . '.express_code=' . $order_table_name . '.express_code')
                    ->where('order_id', $order['id']);
            })->where('order_id', $order['id'])->delete();

            return $orderExpress;
        });
        return [$orderExpress, $needSubscribe];
    }

    /**
     * 订阅物流消息通知(默认快递鸟)
     *
     * @param string $express_code  物流公司编号
     * @param string $express_no    运单号
     * @param object $orderExpress  发货包裹
     * @param object $order  订单
     */
    public function subscribeExpressInfo($express_code, $express_no, $orderExpress, $order)
    {
        $type = 'kdniao';
        if ($type === 'kdniao') {
            try {
                $expressLib = new \addons\shopro\library\Express();
                $expressLib->subscribe([
                    'express_code' => $express_code,
                    'express_no' => $express_no
                ], $orderExpress, $order);
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }
    }

    public function sendStore($id, $item_id = '')
    {
        if ($this->request->isAjax()) {
            $item_id = $item_id ? explode(',', $item_id) : [];

            $order = $this->model->payed()->where('id', $id)->find();

            if (!$order) {
                $this->error('订单不存在或未支付');
            }

            // 查询要发货的商品
            $where = [
                'order_id' => $id,
                'id' => ['in', $item_id],
                'dispatch_type' => ['in', ['store', 'selfetch']],        // 必须是商家配送，和自提
                'dispatch_status' => ['in', [\app\admin\model\shopro\order\OrderItem::DISPATCH_STATUS_NOSEND]],
            ];

            $itemList = \app\admin\model\shopro\order\OrderItem::where($where)->select();

            if (!$itemList) {
                $this->error('没有要发货的订单商品');
            }

            // 对选择的 item 进行发货
            Db::transaction(function () use ($order, $itemList) {
                $order->adminStoreOrderSend($order, $itemList, ['oper_type' => 'admin', 'oper' => $this->auth->getUserInfo()]);
            });

            // 重新获取订单
            $order = $this->model->with(['item'])->where('id', $id)->find();

            return $this->success('发货成功', null, $order);
        }
    }

    /**
     * 获取物流快递信息
     */
    public function getExpress($express_id = 0)
    {
        $type = $this->request->get('type');

        // 获取包裹
        $orderExpress = OrderExpress::with('order')->where('id', $express_id)->find();
        if (!$orderExpress) {
            return $this->error('包裹不存在');
        }

        $expressLib = new \addons\shopro\library\Express();

        try {
            if ($type == 'subscribe') {
                // 重新订阅
                $expressLib->subscribe([
                    'express_code' => $orderExpress['express_code'],
                    'express_no' => $orderExpress['express_no']
                ], $orderExpress, $orderExpress->order);
            } else {
                // 手动查询
                $result = $expressLib->search([
                    'express_code' => $orderExpress['express_code'],
                    'express_no' => $orderExpress['express_no']
                ], $orderExpress, $orderExpress->order);

                // 差异更新物流信息
                $expressLib->checkAndAddTraces($orderExpress, $result);
            }

            $order = $this->model->with(['item'])->where('id', $orderExpress['order_id'])->find();
        } catch (\Exception $e) {
            return $this->error(($type == 'subscribe' ? '订阅失败' : '刷新失败') . $e->getMessage());
        }

        return $this->success(($type == 'subscribe' ? '订阅成功' : '刷新成功'), null, $order);
    }


    /**
     * 订单改价
     *
     * @param int $id
     * @return void
     */
    public function changeFee($id = 0)
    {
        $total_fee = $this->request->post('total_fee');
        $change_msg = $this->request->post('change_msg');
        if ($total_fee <= 0) {
            return $this->error('请输入正确的金额');
        }

        $order = $this->model->nopay()->where('id', $id)->find();

        if (!$order) {
            return $this->error('订单不可改价');
        }

        // 记录原始值
        $current_total_fee = $order->total_fee;
        $order->total_fee = $total_fee;
        $order->last_total_fee = $order->last_total_fee > 0 ? $order->last_total_fee : $current_total_fee;
        $order->save();

        \addons\shopro\model\OrderAction::operAdd($order, null, $this->auth->getUserInfo(), 'admin', "应支付金额由 ￥" . $current_total_fee . " 改为 ￥" . $total_fee . ($change_msg ? "，改价原因：" . $change_msg : ''));

        return $this->success('改价成功', null, $order);
    }


    /**
     * 同意退款
     */
    public function refund($id = 0, $item_id = 0)
    {
        if ($this->request->isAjax()) {
            $refund_money = round($this->request->post('refund_money', 0), 2);

            if ($refund_money < 0) {
                $this->error('退款金额不能小于 0');
            }

            $order = $this->model->where(
                'status',
                'in',
                [
                    \app\admin\model\shopro\order\Order::STATUS_PAYED,
                    \app\admin\model\shopro\order\Order::STATUS_FINISH
                ]
            )
                ->with('item')->where('id', $id)->find();

            if (!$order) {
                $this->error('订单不存在或不可退款');
            }

            $items = $order->item;
            $items = array_column($items, null, 'id');

            // 当前订单已退款总金额
            $refunded_money = array_sum(array_column($items, 'refund_fee'));
            // 剩余可退款金额
            $refund_surplus_money = $order->pay_fee - $refunded_money;
            // 如果退款金额大于订单支付总金额
            if ($refund_money > $refund_surplus_money) {
                $this->error('退款总金额不能大于实际支付金额');
            }

            if ($item_id) {
                $item = $items[$item_id];
                if (!$item || in_array($item['refund_status'], [
                    \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_OK,
                    \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_FINISH,
                ])) {
                    $this->error('订单商品已退款，不能重复退款');
                }
            } else {
                $is_refund = false;
                foreach ($items as $key => $it) {
                    if (in_array($it['refund_status'], [
                        \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_OK,
                        \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_FINISH,
                    ])) {
                        // 已退款
                        unset($items[$key]);
                    } else {
                        $is_refund = true;
                    }
                }
                $items = array_values($items);

                if (!$is_refund) {
                    $this->error('订单已退款，不能重复退款');
                }
            }

            Db::transaction(function () use ($order, $items, $item_id, $refund_money, $refund_surplus_money) {
                if ($item_id) {
                    // 单个商品退款
                    $item = $items[$item_id];
                    \app\admin\model\shopro\order\Order::startRefund($order, $item, $refund_money, $this->auth->getUserInfo(), '管理员操作退款');
                } else {
                    // 全部退款
                    // 未退款 item 商品总金额
                    $goods_total_amount = 0;
                    foreach ($items as $ke => $it) {
                        $goods_total_amount += ($it['goods_price'] * $it['goods_num']);
                    }

                    $current_refunded_money = 0;
                    for ($i = 0; $i < count($items); $i++) {
                        if ($i == (count($items) - 1)) {
                            // 最后一条,全部退完
                            $current_refund_money = $refund_money - $current_refunded_money;
                        } else {
                            // 按比例计算当前 item 应退金额
                            $current_refund_money = round($refund_money * (($items[$i]['goods_price'] * $it['goods_num']) / $goods_total_amount), 2);
                        }
                        if (($current_refunded_money + $current_refund_money) > $refund_money) {
                            $current_refund_money = $refund_money - $current_refunded_money;
                        }

                        if ($current_refund_money >= 0) {       // 支付金额或者退款金额 为 0 也能退
                            $current_refunded_money += $current_refund_money;

                            \app\admin\model\shopro\order\Order::startRefund($order, $items[$i], $current_refund_money, $this->auth->getUserInfo(), '管理员操作退款');
                        }
                    }
                }
            });

            $item_list = \app\admin\model\shopro\order\OrderItem::where(['order_id' => $id])->select();
            return $this->success('操作成功', null, $item_list);
        }
    }


    // 取消订单
    public function cancel($id)
    {
        if ($this->request->isAjax()) {
            $order = $this->model->where('id', $id)->nopay()->find();
            if (!$order) {
                $this->error('订单不存在或已取消');
            }

            $order = $order->doCancel($order, $this->auth->getUserInfo(), 'admin');

            return $this->success('操作成功', null, $order);
        }
    }


    // 修改收货人信息
    public function editConsignee($id)
    {
        if ($this->request->isAjax()) {
            $params = $this->request->post();
            extract($params);

            $row = $this->model->withTrashed()->where('id', $id)->find();
            if (!$row) {
                $this->error('订单不存在');
            }

            try {
                if ($this->modelValidate) {
                    $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                    $row->validateFailException(true)->validate($validate);
                }

                $result = $row->save([
                    'consignee' => $consignee,
                    'phone' => $phone,
                    'province_id' => $province_id,
                    'province_name' => $province_name,
                    'city_id' => $city_id,
                    'city_name' => $city_name,
                    'area_id' => $area_id,
                    'area_name' => $area_name,
                    'address' => $address,
                ], ['id' => $id]);
            } catch (ValidateException $e) {
                $this->error($e->getMessage());
            } catch (PDOException $e) {
                $this->error($e->getMessage());
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $order = $this->model->with('user')->where('id', $id)->find();
                $this->success('修改成功', null, $order);
            } else {
                $this->error(__('No rows were updated'));
            }
        }
    }

    // 编辑商家备注
    public function editMemo($id)
    {
        if ($this->request->isAjax()) {
            $memo = $this->request->post('memo');

            $order = $this->model->withTrashed()->where('id', $id)->find();
            if (!$order) {
                $this->error('订单不存在');
            }

            $order->memo = $memo;
            $order->save();

            \addons\shopro\model\OrderAction::operAdd($order, null, $this->auth->getUserInfo(), 'admin', "修改备注：" . $memo);

            return $this->success('操作成功', null, $order);
        }
    }

    // 打印订单小票
    public function printOrder($sn){
        $myprint = new PrintOrderFei();
        $res = $myprint->printOrder($sn);
        if($res['code'] == 1){
            $this->success('成功');
        } else {
            $this->error($res['msg']);
        }
    }


    // 获取订单操作记录
    public function actions($id)
    {
        $actions = \app\admin\model\shopro\order\OrderAction::with('oper')->where('order_id', $id)->order('id', 'desc')->select();

        foreach ($actions as $key => $action) {
            $action = $action->toArray();
            if ($action['oper_type'] == 'admin') {
                $oper = [
                    'id' => $action['oper_id'],
                    'name' => $action['oper'] ? $action['oper']['nickname'] : ''
                ];
            } else if ($action['oper_type'] == 'user') {
                $oper = [
                    'id' => $action['oper_id'],
                    'name' => '用户'
                ];
            } else if ($action['oper_type'] == 'system') {
                $oper = [
                    'id' => $action['oper_id'],
                    'name' => '系统'
                ];
            } else {
                $oper = null;
            }

            $action['oper'] = $oper;
            $actions[$key] = $action;
        }

        return $this->success('操作成功', null, $actions);
    }


    // 构建查询条件
    private function buildSearchOrder()
    {
        $filter = $this->request->get("filter", '');
        $filter = (array)json_decode($filter, true);
        $filter = $filter ? $filter : [];

        $dispatch_type = isset($filter['dispatch_type']) ? $filter['dispatch_type'] : 'all';
        $status = isset($filter['status']) ? $filter['status'] : 'all';
        $goods_type = isset($filter['goods_type']) ? $filter['goods_type'] : 'all';
        $aftersale_sn = isset($filter['aftersale_sn']) ? $filter['aftersale_sn'] : '';
        $nickname = isset($filter['nickname']) ? $filter['nickname'] : '';
        $mobile = isset($filter['user_phone']) ? $filter['user_phone'] : '';
        $goods_title = isset($filter['goods_title']) ? $filter['goods_title'] : '';
        $store_id = isset($filter['store_id']) ? $filter['store_id'] : 'all';

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

        // 售后单号
        if ($aftersale_sn) {
            $orders = $orders->whereExists(function ($query) use ($aftersale_sn, $tableName) {
                $itemTableName = (new \app\admin\model\shopro\order\Aftersale())->getQuery()->getTable();

                $query->table($itemTableName)->where($itemTableName . '.order_id=' . $tableName . 'id')
                    ->where('aftersale_sn', $aftersale_sn);
            });
        }


        // 快递方式 || 商品类型 (同一个表，写在一起)
        if ($dispatch_type != 'all' || $goods_type != 'all' || $goods_title || $store_id != 'all') {
            $orders = $orders->whereExists(function ($query) use ($dispatch_type, $goods_type, $goods_title, $store_id, $tableName) {
                $itemTableName = (new \app\admin\model\shopro\order\OrderItem())->getQuery()->getTable();

                $query = $query->table($itemTableName)->where($itemTableName . '.order_id=' . $tableName . 'id');

                if ($dispatch_type != 'all') {
                    $query = $query->where('dispatch_type', $dispatch_type);
                }

                if ($goods_type != 'all') {
                    $query = $query->where('goods_type', $goods_type);
                }

                if ($goods_title) {
                    $query = $query->where('goods_title', 'like', "%{$goods_title}%");
                }

                if ($store_id != 'all') {
                    // 门店订单
                    if ($store_id) {
                        $query = $query->where('store_id', $store_id);
                    } else {
                        $query = $query->where('store_id', '<>', 0);
                    }
                }

                return $query;
            });
        }

        // 订单状态
        if ($status != 'all') {
            if (in_array($status, ['invalid', 'cancel', 'nopay', 'nosend', 'noget', 'nocomment', 'aftersale', 'refund', 'payed', 'finish'])) {
                if (in_array($status, ['nosend', 'noget', 'nocomment', 'aftersale', 'refund'])) {
                    $orders = $orders->payed();
                }

                $status = $status == 'refund' ? 'refundStatus' : $status;

                if ($store_id != 'all' && in_array($status, ['nosend', 'noget', 'nocomment', 'aftersale', 'refundStatus'])) {
                    // 查询门店订单,需要增加 store_id 进行查询
                    $status = 'store' . ucfirst($status);
                    $orders = $orders->{$status}($store_id);
                } else {
                    // 所有订单
                    $orders = $orders->{$status}();
                }
            }
        }

        return $orders;
    }
}
