<?php

namespace addons\shopro\listener\order;


use addons\shopro\exception\Exception;
use addons\shopro\model\Cart;
use addons\shopro\model\Config;
use addons\shopro\model\Order;
use addons\shopro\model\Store;
use addons\shopro\model\User;
use PrintOrderFei\PrintOrderFei;
use PrintOrderFei\PrintOrders;
use think\Db;

/**
 * 支付成功
 */
class Payed
{

    // 订单支付成功
    public function orderPayedAfter(&$params) {
        // 订单支付成功
        $order = $params['order'];

<<<<<<< HEAD
        //打印订单
        $sn = $order['order_sn'];
//        $myprint = new PrintOrderFei();
//        $myprint->printOrder($sn);
       
 

       
       

=======
>>>>>>> 4d1b7f5d85e7d8d9a560ce4daa6ca6096617b298
        // 重新查询订单
        $order = Order::with('item')->where('id', $order['id'])->find();
        $items = $order ? $order['item'] : [];

        // 有门店相关的订单
        $storeIds = [];
        foreach ($items as $item) {
            if (in_array($item['dispatch_type'], ['store', 'selfetch']) && $item['store_id']) {
                $storeIds[] = $item['store_id'];
            }
        }

        $data = [];
        if ($storeIds) {
            $data = [];
            // 存在门店，查询门店管理员
            $stores = Store::with(['userStore.user'])->where('id', 'in', $storeIds)->select();
            foreach ($stores as $key => $store) {
                $userStoreList = $store['user_store'] ? : [];
                unset($store['user_store']);

                // 当前门店所有用户管理员
                $userList = [];
                foreach ($userStoreList as $user) {
                    if ($user['user']) {
                        $userList[] = $user['user'];
                    }
                }

                // 有用户才能发送消息
                if ($userList) {
                    $data[] = [
                        'store' => $store,
                        'userList' => $userList
                    ];
                }
            }
        }

        // 存在要通知的门店管理员
        if ($data) {
            // 按门店为单位发送通知
            foreach ($data as $key => $sendData) {
                \addons\shopro\library\notify\Notify::send(
                    $sendData['userList'],
                    new \addons\shopro\notifications\store\Order([
                        'store' => $sendData['store'],
                        'order' => $order,
                        'event' => 'store_order_new'
                    ])
                );
            }
        }

        //打印订单
        $sn = $order['order_sn'];
        $myprint = new PrintOrderFei();
        $myprint->printOrder($sn);
    }

}
