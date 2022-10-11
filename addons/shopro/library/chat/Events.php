<?php

namespace addons\shopro\library\chat;

/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{

    public static function onWorkerStart($businessWorker)
    {
        // 5 秒同步一下 客服当前接待客户数，计算客服忙碌度
        Timer::add(5, function () {
            // 获取当前连接的客服
            $clientIds = Gateway::getClientIdListByGroup(Online::getGrouponName('online_customer_service'));

            foreach ($clientIds as $client_id) {
                $session = Gateway::getSession($client_id);
                $customerService = $session['user'];
                $customer_service_id = $session['uid'] ?? 0;        // 客服 id

                if ($customer_service_id) {
                    // 当前客服分组名
                    $customerServiceGroupName = Online::getGrouponName('customer_service_user', ['customer_service_id' => $customer_service_id]);
                    // 获取并设置当前客服正在服务的用户
                    $customerService['current_num'] = Gateway::getClientIdCountByGroup($customerServiceGroupName);
                    $customerService['busy_percent'] = $customerService['current_num'] / $customerService['max_num'];   // 繁忙程度，越大越繁忙
                    Gateway::updateSession($client_id, ['user' => $customerService]);
                }
            }
        });

        // 初始化上传配置
        Online::uploadConfigInit();
    }


    public static function onWebSocketConnect($client_id, $data) 
    {
        // 存储当前请求信息
        $_SESSION['server'] = $data['server'] ?? [];

        $request = $data['get'];

        $linkerData = [];
        $linkerData['identify'] = $request['identify'] ?? '';
        $linkerData['session_id'] = $request['session_id'] ?? '';
        $linkerData['token'] = $request['token'] ?? '';
        $linkerData['expire_time'] = $request['expire_time'] ?? '';
        $linkerData['customer_service_id'] = $request['customer_service_id'] ?? 0;

        if (empty($linkerData['identify'])) {
            // 缺少参数
            Sender::error($client_id, [
                'type' => 'connect_error',
                'msg' => '连接错误'
            ]);
            Gateway::closeClient($client_id);
            return false;
        }

        // 连接者
        $linker = new Linker($client_id, $linkerData);

        if ($linker->checkAndBind()) {
            // init
            $linker->init();
        }
    }


    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $requestData)
    {
        $requestData = json_decode($requestData, true);
        $identify = $_SESSION['identify'] ?? '';
        $type = $requestData['type'] ?? '';     // 消息类型
        $data = $requestData['data'] ?? [];              // 要做的事件，参数
        $message = $requestData['message'] ?? [];        // 发送的消息

        if (empty($type) || empty($identify)) {
            // 缺少参数
            Sender::error($client_id, [
                'type' => 'params_error',
                'msg' => '参数错误'
            ]);
            Gateway::closeClient($client_id);
            return false;
        }

        if ($type == 'ping') {
            // 心跳检测，直接返回
            return true;
        }

        if ($identify == 'customer_service') {
            $session_id = $requestData['session_id'] ?? '';        // 如果是客服，接受传入的 session_id
        } else {
            $session_id = $_SESSION['uid'] ?? '';
        }

        $linkerData = [
            'identify' => $identify,
        ];

        // 连接者
        $linker = new Linker($client_id, $linkerData);

        $linker->message($session_id, $type, $message, $data);
    }
    
    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        $identify = $_SESSION['identify'] ?? '';
        if (empty($identify)) {
            // 缺少参数
            Sender::error($client_id, [
                'type' => 'params_error',
                'msg' => '参数错误'
            ]);
            Gateway::closeClient($client_id);
            return false;
        }

        // 只能通过 $identify 获取 session
        $linkerData = [
            'identify' => $identify,
        ];

        // 连接者
        $linker = new Linker($client_id, $linkerData);

        $linker->close($client_id);
    }
}
