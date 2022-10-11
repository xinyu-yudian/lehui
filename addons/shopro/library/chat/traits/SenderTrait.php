<?php

namespace addons\shopro\library\chat\traits;

use GatewayWorker\Lib\Gateway;
use addons\shopro\library\chat\Online;

/**
 * 通知基础方法
 */
trait SenderTrait
{
    /**
     * 可以同时给多个 uid 发送，支持 u_id 是数组
     */
    public static function successById($u_id, array $content)
    {
        $result = [
            'code' => 1,
            'msg' => '',
            'type' => '',
            'data' => null
        ];

        $result = array_merge($result, $content);

        Gateway::sendToUid($u_id, json_encode($result));

        return $result;
    }


    public static function successByCustomerServiceId($customer_service_id, array $content)
    {
        return self::successById(Online::getUId($customer_service_id, 'customer_service'), $content);
    }


    public static function successBySessionId($session_id, array $content)
    {
        return self::successById(Online::getUId($session_id, 'user'), $content);
    }


    /**
     * 给一个 client_id 发送消息
     */
    public static function success($client_id, array $content)
    {
        $result = [
            'code' => 1,
            'msg' => '',
            'type' => '',
            'data' => null
        ];

        $result = array_merge($result, $content);
        Gateway::sendToClient($client_id, json_encode($result));

        return $result;
    }

    /**
     * 给所有 client_id 或指定 clientIds 发送
     */
    public static function successAll(array $content, $clientIds = [])
    {
        $result = [
            'code' => 1,
            'msg' => '',
            'type' => '',
            'data' => null
        ];

        $result = array_merge($result, $content);
        Gateway::sendToAll(json_encode($result), $clientIds);

        return $result;
    }



    public static function errorById($u_id, array $content)
    {
        $result = [
            'code' => 0,
            'msg' => '',
            'type' => '',
            'data' => null
        ];

        $result = array_merge($result, $content);
        Gateway::sendToUid($u_id, json_encode($result));

        return $result;
    }


    public static function error($client_id, array $content)
    {
        $result = [
            'code' => 0,
            'msg' => '',
            'type' => '',
            'data' => null
        ];

        $result = array_merge($result, $content);
        Gateway::sendToClient($client_id, json_encode($result));

        return $result;
    }


    public static function __callStatic($name, $arguments)
    {
        // 需要存储数据库的消息，先存储数据库，再发送
        if (strpos($name, 'message') !== false) {
            // 存库
            $customerServiceLog = Online::addMessage($name, $arguments);

            // 将 message 追加到 content 里面
            $content = $arguments[1] ?? [];
            $content['data'] = $content['data'] ?? [];
            $content['data']['message'] = $customerServiceLog->toArray();
            $arguments[1] = $content;

            // 重载方法名
            $currentName = str_replace('message', 'success', $name);
        }

        return self::$currentName(...$arguments);
    }
}


