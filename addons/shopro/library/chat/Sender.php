<?php

namespace addons\shopro\library\chat;

use GatewayWorker\Lib\Gateway;
use addons\shopro\library\chat\traits\SenderTrait;
use addons\shopro\library\chat\traits\GetLinkerTrait;

/**
 * 通知类
 */
class Sender
{
    use SenderTrait, GetLinkerTrait;


    /**
     * 通知客服用户上线
     *
     * @param string $session_id    上线用户
     * @return void
     */
    public static function userOnline($session_id, $chatUser) {
        // 获取所有在线客服的 client_ids 
        $onlineCustomerServiceClientIds = self::onlineCustomerServiceClientIds();

        Sender::successAll([
            'type' => 'user_online',
            'msg' => '用户上线',
            'data' => [
                'session_id' => $session_id,
                'user' => $chatUser,
                'customer_service' => $_SESSION['customer_service'] ? : null,
            ]
        ], $onlineCustomerServiceClientIds);
    }


    /**
     * 通知在线客服用户下线
     *
     * @param string $session_id    下线用户
     * @return void
     */
    public static function userOffline($session_id) {
        // 获取所有在线客服的 client_ids
        $onlineCustomerServiceClientIds = self::onlineCustomerServiceClientIds();

        // 通知在线客服，用户下线
        Sender::successAll([
            'type' => 'user_offline',
            'msg' => '用户下线',
            'data' => [
                'session_id' => $session_id,
            ]
        ], $onlineCustomerServiceClientIds);
    }


    /**
     * 通知所有客服用户已被接入
     *
     * @return void
     */
    public static function userAccessed($session_id, $customerService) {
        // 获取所有在线客服的 client_ids
        $onlineCustomerServiceClientIds = self::onlineCustomerServiceClientIds();

        // 通知在线客服，用户被接入
        Sender::successAll([
            'type' => 'user_accessed',
            'msg' => '用户被接入',
            'data' => [
                'session_id' => $session_id,
                'customer_service' => $customerService
            ]
        ], $onlineCustomerServiceClientIds);
    }


    public static function userAccess($customerService, $session_id, $oper_type = 'user') {
        $customer_service_id = $customerService['id'] ?? 0;
        
        if ($oper_type == 'user') {
            $chatUser = $_SESSION['chat_user'];
        } else {
            $chatUser = Online::getChatUserBySessionId($session_id);
        }

        Sender::successByCustomerServiceId($customer_service_id, [
            'type' => 'user_access',
            'msg' => '有新的用户接入',
            'data' => [
                'session_id' => $session_id,
                'chat_user' => Online::userSetMessage([$chatUser])[0]        // 额外获取最后一条消息
            ]
        ]);
    }


    /**
     * 客服初始化通知
     *
     * @param string $client_id
     * @param array $customerService
     * @return void
     */
    public static function customerServiceInit($client_id, $customerService) {
        // 客服正在服务的用户, 并且追加最后一条 message
        $onlines = Online::userSetMessage(self::customerServiceOnlineUsers($customerService));

        // 在线用户 的 session_id
        $onlineSessionIds = array_column($onlines, 'session_id');

        // 历史服务客户, 并且追加最后一条 message
        $histories = Online::userSetMessage(self::customerServiceHistoryUsers($customerService, $onlineSessionIds));

        // 等待接待用户, 并且追加最后一条 message
        $waitings = Online::userSetMessage(self::onlineWaitingUsers());

        Sender::success($client_id, [
            'type' => 'init',
            'msg' => '连接成功',
            'data' => [
                'client_id' => $client_id,
                'onlines' => $onlines,
                'histories' => $histories,
                'waitings' => $waitings,
                'customer_service' => $_SESSION['user'] ?? null
            ]
        ]);
    }


    /**
     * 通知正在连接的用户，客服上线了
     *
     * @param array $customerService
     * @return void
     */
    public static function customerServiceOnline($customerService) {
        // 通知正在连接的用户，客服上线了
        $userClientIds = self::customerServiceUserClientIds($customerService);

        Sender::successAll([
            'type' => 'customer_service_online',
            'msg' => '客服 ' . $customerService['name'] . ' 上线',
            'data' => [
                'customer_service' => $customerService
            ]
        ], $userClientIds);
    }


    /**
     * 通知正在连接的用户，客服下线
     *
     * @param array $customerService
     * @return void
     */
    public static function customerServiceOffline($customerService) {
        // 通知正在连接的用户，客服下线了
        $userClientIds = self::customerServiceUserClientIds($customerService);

        Sender::successAll([
            'type' => 'customer_service_offline',
            'msg' => '客服 ' . $customerService['name'] . ' 离线',
            'data' => [
                'customer_service' => $customerService
            ]
        ], $userClientIds);
    }



    /**
     * 通知所有在线客服，更新在线客服列表
     *
     * @return void
     */
    public static function customerServiceOnlineList() {
        // 通知所有在线客服，更新当前在线客服列表
        $onlineCustomerServiceClientIds = self::onlineCustomerServiceClientIds();

        // 获取所有在线客服信息，自动过滤重复
        $customerServices = self::onlineCustomerServices();
        
        // 通知在线客服，更新当前在线客服列表
        Sender::successAll([
            'type' => 'customer_service_update',
            'msg' => '更新客服列表',
            'data' => [
                'customer_services' => $customerServices,
            ]
        ], $onlineCustomerServiceClientIds);
    }



    /**
     * 问题快速回答
     *
     * @param array $question
     * @param array $receives
     * @return void
     */
    public static function questionReply($question, $receives) {
        $client_id = $receives['client_id'] ?? '';
        $session_id = $receives['session_id'] ?? '';
        $user_id = $receives['user_id'] ?? '';
        $customer_service_id = $receives['customer_service_id'] ?? 0;
        $customerService = $receives['customer_service'] ?? [];

        $result = null;
        if ($session_id) {
            // 通知用户
            $result = Sender::messageBySessionId($session_id, [
                    'type' => 'message',
                    'msg' => '收到消息',
                    'data' => [
                        'message' => [
                            'message_type' => 'text',
                            'message' => $question['content']
                        ],
                        'customer_service' => $customerService
                    ]
                ], [
                    'sender' => array_merge([
                        'sender_identify' => 'customer_service',
                        'sender_id' => $customer_service_id
                    ], $receives)
                ]
            );
        } 
        
        if ($customer_service_id && $result && isset($result['data']['message'])){
            // 通知客服
            Sender::successByCustomerServiceId($customer_service_id, [
                    'type' => 'message',
                    'msg' => '收到消息',
                    'data' => [
                        'message' => $result['data']['message'],
                        'session_id' => $session_id
                    ]
                ]
            );
        }
    }


    
    /**
     * 删除用户
     *
     * @param string $client_id
     * @param int $result
     * @param array $data
     * @return void
     */
    public static function delUser($client_id, $data = []) {

        Sender::success($client_id, [
            'type' => 'del_success',
            'msg' => '删除成功',
            'data' => $data
        ]);
    }
}
    
