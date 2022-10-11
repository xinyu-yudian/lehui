<?php

namespace addons\shopro\library\chat\linker;

use app\common\library\Auth;
use addons\shopro\library\chat\Online;
use addons\shopro\library\chat\Sender;
use addons\shopro\library\chat\traits\GetLinkerTrait;
use addons\shopro\model\chat\User as ChatUser;
use GatewayWorker\Lib\Gateway;
use app\admin\model\Admin;
use Workerman\Lib\Timer;

class CustomerService
{

    use GetLinkerTrait;

    public $linker = null;      // addons\shopro\library\customerservice\Linker

    public $identify = 'customer_service';

    public $client_id = null;

    public $admin = null;       // 当前客服对应的 fastadmin 管理员

    public $user = null;        // 当前 fastadmin 管理员对应的 客服


    public function __construct($linker, $client_id, $data)
    {
        // 初始化获取当前连接着身份
        $this->linker = $linker;

        $this->client_id = $client_id;           // 当前 client_id
        

        // 获取当前 session
        if (isset($_SESSION['uid']) && !empty($_SESSION['uid']))
        {
            $this->user = $_SESSION['user'];
            $this->admin = $_SESSION['admin'];
        } else {
            $token = $data['token'] ?? '';
            $expire_time = $data['expire_time'] ?? 0;
            $customer_service_id = $data['customer_service_id'] ?? 0;

            $data = Online::checkAdmin($token, $customer_service_id, $expire_time);
            if ($data) {
                $this->user = $data['customerService'];
                $this->admin = $data['admin'];
            }
        }
    }



    /**
     * 检测并绑定 Uid
     */
    public function checkAndBind()
    {
        if (!$this->admin) {
            Sender::error($this->client_id, [
                'type' => 'nologin',
                'msg' => '请先登录管理后台'
            ]);

            Gateway::closeClient($this->client_id);
            return false;
        }

        if (!$this->user) {
            Sender::error($this->client_id, [
                'type' => 'no_customer_service',
                'msg' => '您还不是客服'
            ]);

            Gateway::closeClient($this->client_id);
            return false;
        }

        // 绑定 uid
        Gateway::bindUid($this->client_id, Online::getUId($this->user['id'], 'customer_service'));

        $_SESSION['uid'] = $this->user['id'];
        $_SESSION['user'] = $this->user;
        $_SESSION['admin_id'] = $this->admin ? $this->admin['id'] : 0;
        $_SESSION['admin'] = $this->admin;
        $_SESSION['identify'] = $this->identify;

        return true;
    }


    /**
     * 初始化客服
     */
    public function init() {
        $customerService = $this->user;

        // 客服上线
        Online::customerServiceOnline($this->client_id, $this->user);
    }



    public function message($session_id, $type, $message, $data)
    {
        if ($type == 'message') {
            // 给用户发消息
            Sender::messageBySessionId($session_id, [
                'type' => 'message',
                'msg' => '收到消息',
                'data' => [
                    'message' => $message,
                    'customer_service' => $_SESSION['user']
                ]
            ]);

            Sender::success($this->client_id, [
                'type' => 'receipt',
                'msg' => '发送成功', 
            ]);
        } else if ($type == 'access') {     // 接入
            // 客服信息
            $customerService = $this->user;
            
            // 接入客户
            Online::bindCustomerServiceBySessionId($session_id, $customerService, 'customer_service');

            $msg = '您好，客服 ' . $customerService['name'] . " 为您服务";

            // 通知用户客服接入
            Sender::successBySessionId($session_id, [
                'type' => 'access',
                'msg' => '客服接入',
                'data' => [
                    'message' => [
                        'message_type' => 'system',
                        'message' => $msg,
                        'createtime' => time()
                    ],
                    'customer_service' => $customerService
                ]
            ]);

            // 通知所有客服，这个用户已接入，把这个用户从客服待接入列表删除
            Sender::userAccessed($session_id, $customerService);

            // 通知客服自己，有新的用户接入
            Sender::userAccess($customerService, $session_id, 'customer_service');
        } else if ($type == 'transfer') {     // 转接
            // 当前客服信息
            $customerService = $this->user;
            $new_customer_service_id = $data['customer_service_id'] ?? 0;

            if (!$new_customer_service_id) {
                // 没有传入转接客服 id
                Sender::error($this->client_id, [
                    'type' => 'transfer_error',
                    'msg' => '请选择要转接的客服'
                ]);
            }

            // 获取被转接入的客服, 自动只取用户信息，过滤重复
            $newCustomerService = Self::onlineCustomerServiceById($new_customer_service_id);

            // 不能转接给自己
            if ($new_customer_service_id == $customerService['id']) {
                // 没有传入转接客服 id
                Sender::error($this->client_id, [
                    'type' => 'transfer_error',
                    'msg' => '您不能转接给自己'
                ]);
            }

            // 转接客户,加入新客服，移除老客服
            Online::transferCustomerServiceBySessionId($session_id, $newCustomerService, $customerService, 'customer_service');

            // 通知用户客服已切换
            $msg = '您好，您的客服已由 ' . $customerService['name'] . " 切换为 " . $newCustomerService['name'];
            Sender::successBySessionId($session_id, [
                'type' => 'access',
                'msg' => '新客服接入',
                'data' => [
                    'message' => [
                        'message_type' => 'system',
                        'message' => $msg,
                        'createtime' => time()
                    ],
                    'customer_service' => $newCustomerService
                ]
            ]);

            // 通知所有客服，用户被接入（用户接入客服变了，要改变历史里面的当前服务客服）
            Sender::userAccessed($session_id, $newCustomerService); 

            // 通知新的客服，有新的用户接入
            Sender::userAccess($newCustomerService, $session_id, 'customer_service');

            // 通知当前客服，用户被转接成功
            Sender::successByCustomerServiceId($customerService['id'], [
                'type' => 'transfer_success',
                'msg' => '转接成功',
                'data' => [
                    'session_id' => $session_id
                ]
            ]);
        } else if ($type == 'message_list') {
            // 获取 session_id 身份
            $customerUser = ChatUser::where('session_id', $session_id)->find();

            // 获取消息列表
            $linker = [
                'session_id' => $session_id,
                'user_id' => $customerUser ? $customerUser['user_id'] : 0
            ];

            // 将用户发给自己的消息标记为 已读
            Online::readMessage($linker, 'user');
            // 通知用户消息列表
            $messageList = Online::messageList($linker, $data);
            Sender::success($this->client_id, [
                'type' => 'message_list',
                'msg' => '获取成功',
                'data' => [
                    'message_list' => $messageList
                ]
            ]);
        } else if ($type == 'switch_status') {
            // 客服信息
            $customerService = $this->user;
            $status = $data['status'];
            
            switch($status) {
                case 'online':      // 切换为在线
                    Online::customerServiceOnline($this->client_id, $customerService);
                    break;
                case 'offline':     // 切换为离线
                    Online::customerServiceOffline($this->client_id, $customerService);
                    break;
                case 'busy':        // 切换为忙碌
                    Online::customerServiceBusy($this->client_id, $customerService);
                    break;
            }

            Sender::success($this->client_id, [
                'type' => 'switch_ok',
                'msg' => '切换成功',
                'data' => [
                    'status' => $status
                ]
            ]);
        } else if ($type == 'del_user') {
            $customerService = $this->user;
            $session_id = $data['session_id'];

            // 返回删除条数,如果用户刚连上，connection 还不存在，这里result 不做使用
            $result = Online::delUserBySessionId($customerService, $session_id);

            Sender::delUser($this->client_id, [
                'session_id' => $session_id
            ]);
        } else if ($type == 'del_all_user') {
            $customerService = $this->user;

            // 返回删除条数,如果用户刚连上，connection 还不存在，这里result 不做使用
            $result = Online::delAllUserBySessionId($customerService);

            Sender::delUser($this->client_id);
        }
    }


    public function close()
    {
        $customer_service_id = $_SESSION['uid'];
        $customerService = $_SESSION['user'];
        $admin_id = $_SESSION['admin_id'];

        // 客服下线，只更新当前连接的在线状态
        Online::customerServiceOffline($this->client_id, $customerService);

        // 定时器十秒检测，如果客服是真的下线了，更新为真实下线
        Timer::add(10, function ($customer_service_id, $customerService) {
            Online::customerServiceRealOffline($customer_service_id, $customerService);
        }, [$customer_service_id, $customerService], false);
    }
}
