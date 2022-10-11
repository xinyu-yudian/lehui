<?php

namespace app\admin\controller\shopro\order;

use addons\shopro\model\OrderAftersaleLog;
use app\admin\model\shopro\order\Order;
use app\admin\model\shopro\order\OrderItem;
use app\admin\model\shopro\order\Aftersale as OrderAftersale;
use app\common\controller\Backend;
use think\db;

/**
 * 订单商品明细
 *
 * @icon fa fa-circle-o
 */
class Aftersale extends Backend
{
    
    /**
     * Aftersale模型对象
     * @var \app\admin\model\shopro\order\Aftersale
     */
    protected $model = null;
    protected $orderModel = null;

    public function _initialize()
    {
        parent::_initialize();

        // 手动加载语言包
        $this->loadlang('shopro/order/order');

        $this->model = new \app\admin\model\shopro\order\Aftersale;
        $this->orderModel = new \app\admin\model\shopro\order\Order;
        $this->view->assign("dispatchStatusList", $this->model->getDispatchStatusList());
        $this->view->assign("aftersaleStatusList", $this->model->getAftersaleStatusList());
        $this->view->assign("refundStatusList", $this->model->getRefundStatusList());
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
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            // list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $sort = $this->request->get("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
            $order = $this->request->get("order", "DESC");
            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $search = $this->request->get("search", '');        // 关键字
            $status = $this->request->get("status", 'all');

            $total = $this->buildSearchOrder()      // 返回的是 orderModel
                ->order($sort, $order)
                ->count();

            $list = $this->buildSearchOrder()      // 返回的是 orderModel
                ->with(['aftersale' => function ($query) {
                    $query->removeOption('soft_delete')->alias('a')->field('a.*, u.nickname as user_nickname, u.mobile as user_mobile')->join('user u', 'u.id = a.user_id', 'LEFT');
                }])
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            // foreach ($list as $row) {
            //     $row->visible(['id','aftersale_sn','user_id','type','order_id','order_item_id','goods_id','goods_sku_price_id','goods_sku_text','goods_title','goods_image','goods_original_price','discount_fee','goods_price','goods_num','dispatch_status','dispatch_fee','aftersale_status','refund_status','refund_fee','createtime','updatetime','deletetime', 'user', 'activity_type']);
 
            // }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return $this->success('获取成功', null, $result);
        }
        return $this->view->fetch();
    }


    /**
     * 详情
     */
    public function detail($id)
    {
        if ($this->request->isAjax()) {
            $row = $this->model->withTrashed()->with(['user', 'order' => function($query) {
                $query->removeOption('soft_delete');
            }, 'logs'])->where('id', $id)->find();

            if (!$row) {
                $this->error(__('No Results were found'));
            }

            $result = [
                'detail' => $row,
                'logs' => $row->logs,
                'order' => $row->order,
            ];
         
            return $this->success('获取成功', null, $result);
        }

        $this->assignconfig('id', $id);

        return $this->view->fetch();
    }


    /**
     * 完成售后
     */
    public function finish($id)
    {
        if ($this->request->isAjax()) {
            $aftersale = $this->model->withTrashed()->canOper()->where('id', $id)->find();

            if (!$aftersale) {
                $this->error('售后单不存在或不可完成');
            }

            $order = Order::withTrashed()->where('id', $aftersale->order_id)->find();
            $orderItem = OrderItem::where('id', $aftersale->order_item_id)->find();
            if (!$order || !$orderItem) {
                $this->error('订单或订单商品不存在');
            }

            Db::transaction(function () use ($aftersale, $order, $orderItem) {
                // 售后单完成前
                $data = ['aftersale' => $aftersale, 'order' => $order, 'item' => $orderItem];
                \think\Hook::listen('aftersale_finish_before', $data);

                $aftersale->aftersale_status = \app\admin\model\shopro\order\Aftersale::AFTERSALE_STATUS_OK;    // 售后完成
                $aftersale->save();
                // 增加售后单变动记录、
                OrderAftersaleLog::operAdd($order, $aftersale, $this->auth->getUserInfo(), 'admin', [
                    'reason' => '卖家完成售后',
                    'content' => '售后订单已完成',
                    'images' => []
                ]);

                $orderItem->aftersale_status = OrderItem::AFTERSALE_STATUS_OK;
                $orderItem->save();
                \addons\shopro\model\OrderAction::operAdd($order, $orderItem, $this->auth->getUserInfo(), 'admin', '管理员完成售后');

                // 售后单完成之后
                $data = ['aftersale' => $aftersale, 'order' => $order, 'item' => $orderItem];
                \think\Hook::listen('aftersale_finish_after', $data);
            });

            return $this->success('操作成功');
        }
    }


    /**
     * 拒绝售后
     */
    public function refuse($id = 0)
    {
        if ($this->request->isAjax()) {
            $refuse_msg = $this->request->post('refuse_msg', '');

            if (!$refuse_msg) {
                $this->error('请输入拒绝原因');
            }

            $aftersale = $this->model->withTrashed()->canOper()->where('id', $id)->find();

            if (!$aftersale) {
                $this->error('售后单不存在或不可拒绝');
            }

            $order = Order::withTrashed()->where('id', $aftersale->order_id)->find();
            $orderItem = OrderItem::where('id', $aftersale->order_item_id)->find();
            if (!$order || !$orderItem) {
                $this->error('订单或订单商品不存在');
            }

            Db::transaction(function () use ($aftersale, $order, $orderItem, $refuse_msg) {
                // 售后单拒绝前
                $data = ['aftersale' => $aftersale, 'order' => $order, 'item' => $orderItem];
                \think\Hook::listen('aftersale_refuse_before', $data);

                $aftersale->aftersale_status = \app\admin\model\shopro\order\Aftersale::AFTERSALE_STATUS_REFUSE;    // 售后拒绝
                $aftersale->save();
                // 增加售后单变动记录
                OrderAftersaleLog::operAdd($order, $aftersale, $this->auth->getUserInfo(), 'admin', [
                    'reason' => '卖家拒绝售后',
                    'content' => $refuse_msg,
                    'images' => []
                ]);

                $orderItem->aftersale_status = OrderItem::AFTERSALE_STATUS_REFUSE;    // 拒绝售后
                $orderItem->save();

                \addons\shopro\model\OrderAction::operAdd($order, $orderItem, $this->auth->getUserInfo(), 'admin', '管理员拒绝订单售后：' . $refuse_msg);

                // 售后单拒绝后
                $data = ['aftersale' => $aftersale, 'order' => $order, 'item' => $orderItem];
                \think\Hook::listen('aftersale_refuse_after', $data);
            });

            return $this->success('操作成功');
        }
    }


    /**
     * 同意退款
     */
    public function refund($id = 0)
    {
        if ($this->request->isAjax()) {
            $refund_money = round($this->request->post('refund_money', 0), 2);

            if ($refund_money < 0) {
                $this->error('退款金额不能小于 0');
            }

            $aftersale = $this->model->withTrashed()->canOper()->where('id', $id)->find();

            if (!$aftersale) {
                $this->error('售后单不存在或不可退款');
            }

            $order = Order::withTrashed()->with('item')->where('id', $aftersale->order_id)->find();
            
            if (!$order) {
                $this->error('订单不存在');
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

            $orderItem = $items[$aftersale['order_item_id']];

            if (!$orderItem || in_array($orderItem['refund_status'], [
                \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_OK,
                \app\admin\model\shopro\order\OrderItem::REFUND_STATUS_FINISH,
            ])) {
                $this->error('订单商品已退款，不能重复退款');
            }

            Db::transaction(function () use ($aftersale, $order, $orderItem, $refund_money, $refund_surplus_money) {
                $data = ['aftersale' => $aftersale, 'order' => $order, 'item' => $orderItem];
                \think\Hook::listen('aftersale_finish_before', $data);
                
                $aftersale->aftersale_status = \app\admin\model\shopro\order\Aftersale::AFTERSALE_STATUS_OK;    // 售后同意
                $aftersale->refund_status = \app\admin\model\shopro\order\Aftersale::REFUND_STATUS_FINISH;    // 售后同意退款
                $aftersale->refund_fee = $refund_money;     // 退款金额
                $aftersale->save();

                // 增加售后单变动记录
                OrderAftersaleLog::operAdd($order, $aftersale, $this->auth->getUserInfo(), 'admin', [
                    'reason' => '卖家同意退款',
                    'content' => '售后订单已退款',
                    'images' => []
                ]);

                $orderItem->aftersale_status = OrderItem::AFTERSALE_STATUS_OK;
                $orderItem->save();
                \addons\shopro\model\OrderAction::operAdd($order, $orderItem, $this->auth->getUserInfo(), 'admin', '管理员同意售后退款');

                // 退款
                \app\admin\model\shopro\order\Order::startRefund($order, $orderItem, $refund_money, $this->auth->getUserInfo(), '管理员同意售后退款');
                
                $data = ['aftersale' => $aftersale, 'order' => $order, 'item' => $orderItem];
                \think\Hook::listen('aftersale_finish_after', $data);
            });

            return $this->success('操作成功');
        }
    }


    /**
     * 留言
     */
    public function addLog($id = 0)
    {
        if ($this->request->isAjax()) {
            $reason = $this->request->post('reason', '卖家留言');
            $content = $this->request->post('content', '');
            $images = $this->request->post('images', []);

            if (!$content) {
                $this->error('留言内容不能为空');
            }

            $aftersale = $this->model->withTrashed()->where('id', $id)->find();

            if (!$aftersale) {
                $this->error('售后单不存在');
            }

            $order = Order::withTrashed()->with('item')->where('id', $aftersale->order_id)->find();

            if (!$order) {
                $this->error('订单不存在');
            }

            Db::transaction(function () use ($order, $aftersale, $reason, $content, $images) {
                if ($aftersale['aftersale_status'] == 0) {
                    $aftersale->aftersale_status = \app\admin\model\shopro\order\Aftersale::AFTERSALE_STATUS_AFTERING;    // 售后处理中
                    $aftersale->save();
                }
                
                // 增加售后单变动记录
                OrderAftersaleLog::operAdd($order, $aftersale, $this->auth->getUserInfo(), 'admin', [
                    'reason' => $reason,
                    'content' => $content,
                    'images' => $images
                ]);
            });

            return $this->success('操作成功');
        }
    }


    private function buildSearchOrder() {
        $search = $this->request->get("search", '');        // 关键字
        $status = $this->request->get("status", 'all');

        $orders = $this->orderModel->withTrashed();

        $orders = $orders->whereExists(function ($query) use ($search, $status) {
            extract($this->getModelTable());
            $aftersales = $query->table($aftersaleName)->where($aftersaleName . '.order_id=' . $orderName . '.id');

            $aftersales = $this->aftersaleSearch($aftersales, $search, $status);

            return $aftersales;
        });

        return $orders;
    }


    private function aftersaleSearch($aftersales, $search, $status) {
        extract($this->getModelTable());

        if ($search) {
            // 模糊搜索字段
            $searcharr = ['goods_title', 'goods_sku_text', 'aftersale_sn'];
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $aftersaleName . '.' . $v : $v;
            }
            unset($v);
            $aftersales = $aftersales->where(function ($query) use ($searcharr, $search, $aftersaleName) {
                $query->where(implode("|", $searcharr), "LIKE", "%{$search}%")
                ->whereOr(function ($query) use ($search, $aftersaleName) {                  // 用户
                    $query->whereExists(function ($query) use ($search, $aftersaleName) {
                        $userTableName = (new \app\admin\model\User())->getQuery()->getTable();

                        $query->table($userTableName)->where($userTableName . '.id=' . $aftersaleName . '.user_id')
                        ->where(function ($query) use ($search) {
                            $query->where('nickname', 'like', "%{$search}%")
                            ->whereOr('mobile', 'like', "%{$search}%");
                        });
                    });
                });
            });
        }

        // 售后单状态
        if ($status != 'all') {
            if (in_array($status, ['cancel', 'refuse', 'nooper', 'ing', 'finish'])) {
                $aftersales = $aftersales->where(OrderAftersale::getScopeWhere($status));
            }
        }

        return $aftersales;
    }


    private function getModelTable () {
        $orderName = $this->orderModel->getQuery()->getTable();
        $aftersaleName = $this->model->getQuery()->getTable();

        return compact("orderName", "aftersaleName");
    }
}
