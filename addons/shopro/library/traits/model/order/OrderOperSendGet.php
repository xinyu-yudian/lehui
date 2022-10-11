<?php

namespace addons\shopro\library\traits\model\order;

use addons\shopro\exception\Exception;
use addons\shopro\model\Dispatch;
use addons\shopro\model\DispatchAutosend;
use addons\shopro\model\DispatchSelfetch;
use addons\shopro\model\DispatchStore;
use addons\shopro\model\Goods;
use addons\shopro\model\Verify;
use addons\shopro\model\User;
use addons\shopro\model\Order;
use addons\shopro\model\OrderAction;
use addons\shopro\model\OrderItem;
use addons\shopro\model\Store;
use think\Cache;
use think\Db;

trait OrderOperSendGet
{
    /**
     * 支付完成检测并发货，检测拼团是否成功（grouponCheckAndSend），比这个先执行
     */
    public function checkDispatchAndSend($order, $params)
    {
        $user = $params['user'] ?? null;
        if (!$user) {
            $user = User::where('id', $order['user_id'])->find();
        }

        // 拼团不自动发货，等成团完成才发货
        $orderExt = $order['ext_arr'];
        $buy_type = ($orderExt && isset($orderExt['buy_type'])) ? $orderExt['buy_type'] : '';
        if (strpos($order['activity_type'], 'groupon') !== false && $buy_type == 'groupon') {
            // 拼团订单，并且是拼团购买（拼团不能加入购物车，一个订单只能有一个商品），自动发货要等到拼团成功才进行
            return true;
        }

        // 检测需要自动发货的 item
        $this->systemCheckAutoSend($order);

        return true;
    }


    /**
     * 拼团完成，检测需要自动发货的商品，并发货（执行的是被 checkDispatchAndSend 方法 过滤掉的拼团的自动发货订单）
     */
    public function grouponCheckAndSend($order)
    {
        $this->systemCheckAutoSend($order);

        return true;
    }

    /**
     * 系统检测自动发货
     */
    private function systemCheckAutoSend($order)
    {
        // 判断订单是否有需要发货的商品，并进行自动发货（selfetch虚拟自提，autosend）
        foreach ($order->item as $key => $item) {
            // 判断不是未发货状态，或者退款完成，continue
            if (
                $item['dispatch_status'] != \addons\shopro\model\OrderItem::DISPATCH_STATUS_NOSEND
                || in_array($item['refund_status'], [OrderItem::REFUND_STATUS_OK, OrderItem::REFUND_STATUS_FINISH])
            ) {
                // 订单已发货，或者已完成退款
                continue;
            }
            switch ($item['dispatch_type']) {
                case 'selfetch':
                    if ($item['goods_type'] == 'virtual') {
                        // 虚拟商品，核销券，自动发货
                        $this->selfetchSendItem($order, $item, ['oper_type' => 'system']);
                    }
                    break;
                case 'autosend':
                    // 自动发货
                    $this->autoSendItem($order, $item, ['oper_type' => 'system']);
            }
        }
    }



    /**
     * 门店订单发货
     */
    public function storeOrderSend($order)
    {
        $store = Store::info();

        foreach ($order['item'] as $key => $item) {
            switch ($item['dispatch_type']) {
                case 'selfetch':
                    $this->selfetchSendItem($order, $item, ['oper_type' => 'store', 'oper' => $store]);
                    break;
                case 'store':
                    // 商家配送
                    $this->storeSendItem($order, $item, ['oper_type' => 'store', 'oper' => $store]);
            }
        }
    }


    /**
     * admin 门店订单发货
     */
    public function adminStoreOrderSend($order, $items, $data)
    {
        foreach ($items as $key => $item) {
            switch ($item['dispatch_type']) {
                case 'selfetch':
                    $this->selfetchSendItem($order, $item, $data);
                    break;
                case 'store':
                    // 商家配送
                    $this->storeSendItem($order, $item, $data);
            }
        }
    }


    /**
     * autosend 单商品自动发货
     */
    private function autoSendItem($order, $item, $data = [])
    {
        // 获取配送模板
        $dispatch = Dispatch::where('type', $item['dispatch_type'])->where('id', $item['dispatch_id'])->find();

        $dispatch_autosend_ids = explode(',', $dispatch->type_ids);
        $dispatchAutosend = DispatchAutosend::where('id', 'in', $dispatch_autosend_ids)
            ->order('id', 'asc')->find();

        $ext = [];
        if ($dispatchAutosend) {
            if (in_array($dispatchAutosend['type'], ['text', 'params'])) {
                $ext['autosend_type'] = $dispatchAutosend['type'];
                $autosend_content = $dispatchAutosend['content'];
                if ($dispatchAutosend['type'] == 'params') {
                    $autosend_content = DispatchAutosend::getParamsContent($autosend_content);
                }
                $ext['autosend_content'] = $autosend_content;
            } else if ($dispatchAutosend['type'] == 'card') {
                // 电子卡密，待补充
            }
        }

        $this->sendItem($order, $item, [
            'ext' => $ext,
            "oper_type" => $data['oper_type'],
        ]);
    }


    /**
     * selfetch 自提/到店 单商品发货
     */
    private function selfetchSendItem($order, $item, $data = [])
    {
        // 获取配送模板
        $dispatch = Dispatch::where('type', $item['dispatch_type'])->where('id', $item['dispatch_id'])->find();
        $type_ids = $dispatch['type_ids'] ?? '';
        $dispatch_selfetch_ids = explode(',', $type_ids);

        $dispatchSelfetch = DispatchSelfetch::where('id', 'in', $dispatch_selfetch_ids)
            ->order('id', 'asc')->find();

        $expiretime = null;       // 核销券过期时间
        if ($dispatchSelfetch) {
            if ($dispatchSelfetch['expire_type'] == 'day') {
                $expire_day = $dispatchSelfetch['expire_day'] > 0 ? $dispatchSelfetch['expire_day'] : 0;
                $expiretime = $expire_day ? (time() + ($expire_day * 86400)) : null;
            } else {
                $expiretime = $dispatchSelfetch['expire_time'] ?: null;
            }
        }

        if ($item['goods_type'] == 'virtual') {
            // 商品购买多件，自动生成多个码
            for ($i = 0; $i < $item['goods_num']; $i++) {
                $this->createVerify($order, $item, [
                    'expiretime' => $expiretime
                ]);
            }
        } else {
            // 商品购买多件，只生成一个码
            $this->createVerify($order, $item, [
                'expiretime' => $expiretime
            ]);
        }

        $this->sendItem($order, $item, [
            "oper_type" => $data['oper_type'],
        ]);
    }


    /**
     * store 商家配送 单商品发货
     */
    private function storeSendItem($order, $item, $data = [])
    {
        // 获取配送模板
        $dispatch = Dispatch::where('type', $item['dispatch_type'])->where('id', $item['dispatch_id'])->find();
        $type_ids = $dispatch['type_ids'] ?? '';
        $dispatch_store_ids = explode(',', $type_ids);
        $dispatchStore = DispatchStore::where('id', 'in', $dispatch_store_ids)
            ->order('id', 'asc')->find();

        $expiretime = null;       // 核销券过期时间
        if ($dispatchStore) {
            // 可以干一些事
        }

        $this->sendItem($order, $item, [
            "oper_type" => $data['oper_type'],
        ]);
    }


    // 发货
    public function sendItem($order, $item, $data = [])
    {
        // 订单发货前
        $hookData = ['order' => $order, 'item' => $item];
        \think\Hook::listen('order_send_before', $hookData);

        $item->express_name = $data['express_name'] ?? null;
        $item->express_code = $data['express_code'] ?? null;
        $item->express_no = $data['express_no'] ?? null;
        $ext = ['send_time' => time()];
        if (isset($data['ext']) && $data['ext']) {
            $ext = array_merge($ext, $data['ext']);
        }
        $item->ext = json_encode($item->setExt($item, $ext));
        $item->dispatch_status = \addons\shopro\model\OrderItem::DISPATCH_STATUS_SENDED;    // 已发货状态

        $item->save();

        $oper_type = $data['oper_type'] ?? 'system';
        $oper = $data['oper'] ?? null;
        extract($this->getOper($oper_type, $oper));

        \addons\shopro\model\OrderAction::operAdd($order, $item, $oper, $oper_type, $oper_iden . '发货订单');

        // 订单发货后
        $hookData = ['order' => $order, 'item' => $item];
        \think\Hook::listen('order_send_after', $hookData);

        return $item;
    }



    /**
     * 核销二维码并确认收货
     */
    public function verifyGeted($order, $item, $verify, $data = [])
    {
        // 获取操作人
        $oper_type = $data['oper_type'] ?? 'system';
        $oper = $data['oper'] ?? null;
        extract($this->getOper($oper_type, $oper));

        // 使用掉核销券
        $verify->usetime = time();
        $verify->oper_type = $oper_type;
        $verify->oper_id = $oper ? $oper['id'] : 0;
        $verify->save();

        // 查询当前订单 item 所有核销券
        $verifiy_num = Verify::canUse()->where('order_item_id', $item['id'])
            ->where('type', 'verify')
            ->count();

        // 判断所有核销码全部核销才能确认收货
        if (!$verifiy_num) {
            // 没有可用优惠券，订单收货
            $this->getedItem($order, $item, $data);
        }
    }



    /**
     * 收货订单
     */
    public function getedItem($order, $item, $data = [])
    {
        // 订单确认收货前
        $hookData = ['order' => $order, 'item' => $item];
        \think\Hook::listen('order_confirm_before', $hookData);

        $ext = ['confirm_time' => time()];
        if (isset($data['ext']) && $data['ext']) {
            $ext = array_merge($ext, $data['ext']);
        }
        $item->ext = json_encode($item->setExt($item, $ext));
        $item->dispatch_status = OrderItem::DISPATCH_STATUS_GETED;        // 确认收货
        $item->save();

        $oper_type = $data['oper_type'] ?? 'system';
        $oper = $data['oper'] ?? null;
        extract($this->getOper($oper_type, $oper));
        OrderAction::operAdd($order, $item, $oper, $oper_type, $oper_iden . '确认收货');

        // 订单确认收货后
        $hookData = ['order' => $order, 'item' => $item];
        \think\Hook::listen('order_confirm_after', $hookData);

        return $order;
    }



    /**
     * 生成码
     */
    private function createVerify($order, $item = null, $data = [])
    {
        $verify = new Verify();

        $verify->user_id = $order['user_id'];
        $verify->type = 'verify';

        $verify->order_id = $order['id'];
        $verify->order_item_id = $item['id'] ?? 0;
        $verify->expiretime = $data['expiretime'];

        $i = 0;
        do {
            // 循环生成唯一核销码
            $is_error = false;
            try {
                $verify->code = Verify::getCode();
                $verify->save();
            } catch (\think\exception\PDOException $e) {
                if ($e->getCode() == '10501') {
                    $is_error = true;
                }
                $i++;

                if ($i > 5) {
                    if (strpos(request()->url(), 'addons') !== false) {
                        // 接口响应
                        new Exception('核销码生成失败');
                    } else {
                        // 后台响应
                        throw new \Exception('核销码生成失败');
                    }
                }
            }
        } while ($is_error);
    }


    /**
     * 根据 oper_type 获取对应的用户
     */
    private function getOper($oper_type, $origin_oper = null)
    {
        $oper = null;
        $oper_iden = '系统自动';
        if ($oper_type == 'system') {
            // 系统自动操作
            $oper = null;
            $oper_iden = '系统自动';
        } else if ($oper_type == 'user') {
            // 用户操作
            $oper = $origin_oper ?: User::info();
            $oper_iden = '用户';
        } else if ($oper_type == 'admin') {
            // 管理员操作
            $oper = $origin_oper ?: null;
            $oper_iden = '管理员';
        } else if ($oper_type == 'store') {
            // 门店管理员操作
            $oper = $origin_oper ?: Store::info();
            $oper_iden = '门店管理员';
        }

        return compact("oper", "oper_iden");
    }
}
