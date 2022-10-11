<?php

namespace addons\shopro\library\chat\traits;

use GatewayWorker\Lib\Gateway;
use addons\shopro\library\chat\Online;
use addons\shopro\model\chat\User as ChatUser;

/**
 * 获取各种连接者身份列表
 */
trait GetLinkerTrait
{

    /**
     * 获取所有在线客服的 client_ids 
     *
     * @return array
     */
    public static function onlineCustomerServiceClientIds() {
        $onlineCustomerServiceClientIds = Gateway::getClientIdListByGroup(Online::getGrouponName('online_customer_service'));

        return $onlineCustomerServiceClientIds ? : [];
    }


    /**
     * 获取所有在线客服 的sessions,一个客服多浏览器登录会重复，因为是session这里都保留
     *
     * @return array
     */
    public static function onlineCustomerServiceSessions()
    {
        // 获取所有在线客服 session
        $customerServiceUserSessions = Gateway::getClientSessionsByGroup(Online::getGrouponName('online_customer_service'));

        return $customerServiceUserSessions ? : [];
    }


    /**
     * 获取所有在线客服的信息，自动过滤重复的信息，因为一个客服可以多浏览器登录
     *
     * @return array
     */
    public static function onlineCustomerServices() {
        $customerServiceSessions = self::onlineCustomerServiceSessions();
        $customerServices = array_column($customerServiceSessions, 'user');

        // 过滤重复
        $newServices = [];
        foreach ($customerServices as $customerService) {
            $newServices[$customerService['id']] = $customerService;
        }

        return array_values($newServices);
    }


    /**
     * 通过 客服 id 获取客服 session， 一个客服多浏览器登录会重复，因为是session这里都保留，当前的客服的session 是老的，不会更新
     *
     * @param int $customer_service_id
     * @return array
     */
    public static function onlineCustomerServiceSessionById($customer_service_id)
    {
        $client_ids = Gateway::getClientIdByUid(Online::getUId($customer_service_id, 'customer_service'));

        $customerServiceSessions = [];
        // 当前客服绑定的所有session
        foreach ($client_ids as $client_id) {
            $customerServiceSessions[] = Gateway::getSession($client_id);
        }

        return $customerServiceSessions;
    }


    /**
     * 通过 客服 id 获取客服 session， 一个客服多浏览器登录会重复，因为是session这里都保留，通过 client_id 判断并拿到当前客服最新session
     *
     * @param int $customer_service_id
     * @return array
     */
    public static function onlineCustomerServiceNewSessionById($customer_service_id, $current_client_id)
    {
        $client_ids = Gateway::getClientIdByUid(Online::getUId($customer_service_id, 'customer_service'));

        $customerServiceSessions = [];
        // 当前客服绑定的所有session
        foreach ($client_ids as $client_id) {
            if ($client_id == $current_client_id) {
                $customerServiceSession = $_SESSION;
            } else {
                // 如果不是自己
                $customerServiceSession = Gateway::getSession($client_id);
            }

            $customerServiceSessions[] = $customerServiceSession;
        }

        return $customerServiceSessions;
    }


    /**
     * 通过客服 id 获取 客服信息
     *
     * @param int $customer_service_id
     * @return array
     */
    public static function onlineCustomerServiceById($customer_service_id)
    {
        // 客服多浏览器登录的时候是多个 session
        $customerServiceSessions = self::onlineCustomerServiceSessionById($customer_service_id);

        // 只需要获取第一条的 user 信息
        $customerService = (isset($customerServiceSessions[0]) && isset($customerServiceSessions[0]['user'])) ? $customerServiceSessions[0]['user'] : [];

        return $customerService;
    }


    /**
     * 当前正在等待接入的用户 session
     *
     * @return array
     */
    public static function onlineWaitingUserSessions () {
        $onlineWaitingUserSessions = Gateway::getClientSessionsByGroup(Online::getGrouponName('online_waiting'));

        return $onlineWaitingUserSessions ? : [];
    }



    /**
     * 当前正在等待接入的用户
     *
     * @return array
     */
    public static function onlineWaitingUsers() {
        $waitingSessionUsers = self::onlineWaitingUserSessions();

        $waitings = array_column($waitingSessionUsers, 'chat_user');

        // 过滤重复
        $waitingUsers = [];
        foreach ($waitings as $waiting) {
            $waitingUsers[$waiting['id']] = $waiting;
        }

        return array_values($waitingUsers);
    }


    /**
     * 获取当前客服正在服务的用户 session, 这里同一个用户可能会存在两个 session（多端登录）
     *
     * @param array $customerService
     * @return array
     */
    public static function customerServiceUserSessions($customerService) {
        // 获取当前客服 group_name
        $customerServiceGroupName = Online::getGrouponName('customer_service_user', ['customer_service_id' => $customerService['id']]);
        // 获取当前客服正在服务的用户列表 client_id
        $customerServiceUserSessions = Gateway::getClientSessionsByGroup($customerServiceGroupName);

        return $customerServiceUserSessions ? : [];
    }



    /**
     * 获取当前客服正在服务的用户 （去重）
     *
     * @param array $customerService
     * @return array
     */
    public static function customerServiceOnlineUsers($customerService) {
        // 当前客服服务的所有用户 session
        $customerServiceUserSessions = self::customerServiceUserSessions($customerService);
        // 在线用户列表
        $onlines = array_column($customerServiceUserSessions, 'chat_user');

        // 过滤重复
        $newUsers = [];
        foreach ($onlines as $online) {
            $newUsers[$online['id']] = $online;
        }

        return array_values($newUsers);
    }


    /**
     * 获取当前客服正在服务的用户 client_ids
     *
     * @param array $customerService
     * @return array
     */
    public static function customerServiceUserClientIds($customerService) {
        // 获取当前客服 group_name
        $customerServiceGroupName = Online::getGrouponName('customer_service_user', ['customer_service_id' => $customerService['id']]);
        // 获取当前客服正在服务的用户列表 client_id
        $userClientIds = Gateway::getClientIdListByGroup($customerServiceGroupName);

        return $userClientIds ? : [];
    }



    /**
     * 获取当前客服服务的历史用户
     *
     * @param array $customerService
     * @param array $exceptSessionIds
     * @return array
     */
    public static function customerServiceHistoryUsers($customerService, Array $exceptSessionIds = []) {
        $histories = Online::historyByCustomerServiceId($customerService, ['except' => $exceptSessionIds]);
        foreach ($histories as $key => &$history) {
            $status = Gateway::isUidOnline(Online::getUId($history['session_id'], 'user'));
            $history['status'] = $status;           // 在线状态
            $history['customer_service'] = null;    // 如果在线，并且已经接入客服，当前客服信息
            if ($status) {
                // 在线，判断用户是否正在被客服接入
                $client_ids = Gateway::getClientIdByUid(Online::getUId($history['session_id'], 'user'));
                $client_id = current($client_ids);
                $historySession = Gateway::getSession($client_id);
                $customerService = $historySession['customer_service'] ? : null;
                $history['customer_service'] = $customerService;
            }
            $history['status'] = $status;
        }

        return $histories;
    }



    /**
     * 通过 session_id 获取连接的用户对应的 chatUser
     *
     * @param string $session_id
     * @return object
     */
    public static function getChatUserBySessionId($session_id)
    {
        $chatUser = ChatUser::where('session_id', $session_id)->find();
        return $chatUser;
    }


    /**
     * 通过 user_id 获取连接的用户对应的 chatUser
     *
     * @param string $user_id
     * @return object
     */
    public static function getChatUserByUserId($user_id)
    {
        $chatUser = ChatUser::where('user_id', $user_id)->find();
        return $chatUser;
    }
}
