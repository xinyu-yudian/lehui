<?php

namespace addons\shopro\library\chat\linker;

use app\common\library\Auth;
use addons\shopro\library\chat\Online;
use addons\shopro\library\chat\Sender;
use GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;

class User 
{
    public $linker = null;      // addons\shopro\library\customerservice\Linker

    public $identify = 'user';

    public $session_id = null;  // 前端用户标识

    public $client_id = null;

    public $user = null;        // 当前 fastadmin 用户

    public $chatUser = null;     // 当前用户对应的顾客表（chat_user） 信息 

    public function __construct($linker, $client_id, $data)
    {
        // 初始化获取当前连接着身份
        $this->linker = $linker;
        $this->client_id = $client_id;           // 当前 client_id

        if (isset($_SESSION['uid']) && !empty($_SESSION['uid'])) {
            $this->session_id = $_SESSION['uid'] ?? '';
            $this->user = $_SESSION['user'] ?? null;
        } else {
            $token = $data['token'] ?? '';                     // fastadmin token
            $this->session_id = $data['session_id'] ?? '';           // session_id 如果没有，则后端生成
            
            // 根据 token 获取对应的 fastadmin 用户
            if ($token) {
                $user = Online::checkUser($token);
                if ($user) {
                    $this->user = $user;
                }
                $_SESSION['user'] = $this->user;
            }

            // 初始化连接，需要获取 session_id
            if (!$this->session_id) {
                // 如果没有 session_id
                if ($this->user) {
                    // 如果存在 user
                    $chatUser = Online::getChatUserByUserId($this->user['id']);
                    $this->session_id = $chatUser ? $chatUser['session_id'] : '';
                }
            }

            if (!$this->session_id) {
                // 如果依然没有 session_id, 随机生成 session_id
                $this->session_id = md5(time() . mt_rand(1000000, 9999999));
            }
        }

        // 更新顾客用户信息
        $this->chatUser = Online::updateChatUser($this->session_id, $this->user);
        $this->chatUser['status'] = 1;       // 用户在线状态：在线
        $_SESSION['chat_user'] = $this->chatUser;     // 转为数组
    }


    /**
     * 检测并绑定 Uid
     */
    public function checkAndBind() 
    {

        if (!$this->session_id) {
            Gateway::closeClient($this->client_id);
            return false;
        }

        // 绑定 uid
        Gateway::bindUid($this->client_id, Online::getUId($this->session_id, 'user'));

        $_SESSION['uid'] = $this->session_id;
        $_SESSION['user_id'] = $this->user ? $this->user['id'] : 0;
        $_SESSION['user'] = $this->user;
        $_SESSION['identify'] = $this->identify;
        $_SESSION['customer_service_id'] = 0;       // 分配的客服id 
        $_SESSION['customer_service'] = [];       // 分配的客服 

        // 加入在线用户组
        Gateway::joinGroup($this->client_id, Online::getGrouponName('online_user'));

        return true;
    }


    /**
     * 初始化用户
     */
    public function init() 
    {
        $user_id = $this->user ? $this->user['id'] : 0;

        // 用户初始化成功
        Sender::success($this->client_id, [
            'type' => 'init',
            'msg' => '连接成功',
            'data' => [
                'client_id' => $this->client_id,
                'session_id' => $this->session_id
            ]
        ]);

        if ($user_id) {
            // 处理用户登录
            Online::sessionUserSave($this->user, $this->session_id);
        }

        // 分配客服
        $currentCustomerService = Online::allocatCustomerService($this->session_id, $user_id);

        if ($currentCustomerService) {
            // 记录客服信息，并将用户加入客服组
            Online::bindCustomerService($this->client_id, $currentCustomerService, 'user');

            // 通知用户已连接上客服
            $msg = '您好，客服 ' . $currentCustomerService['name'] . " 为您服务";
            Sender::successBySessionId($this->session_id, [
                'type' => 'access',
                'msg' => '客服接入',
                'data' => [
                    'message' => [
                        'message_type' => 'system',
                        'message' => $msg,
                        'createtime' => time()
                    ],
                    'customer_service' => $currentCustomerService
                ]
            ]);

            // 通知所有客服用户上线
            Sender::userOnline($this->session_id, $this->chatUser);

            // 通知所有客服，用户被接入，要把当前用户从别的客服的回话中列表移除
            Sender::userAccessed($this->session_id, $currentCustomerService);

            // 通知新的客服，有新的用户接入
            Sender::userAccess($currentCustomerService, $this->session_id, 'user');
        } else {
            Sender::success($this->client_id, [
                'type' => 'waiting',
                'msg' => '客服不在线',
                'data' => [
                    'message' => [
                        'message_type' => 'system',
                        'message' => '当前没有客服在线，请耐心等待客服接入',
                        'createtime' => time()
                    ]
                ]
            ]);

            // 加入等待分配客服分组
            Online::bindWaiting($this->client_id);
        }
    }



    /**
     * 消息处理
     */
    public function message ($session_id, $type, $message, $data) 
    {
        $customerService = $_SESSION['customer_service'];
        $customer_service_id = $customerService ? $customerService['id'] : 0;

        if ($type == 'message') {
            // 过滤 message
            if ($message['message_type'] == 'text') {
                $message['message'] = trim(strip_tags($message['message']));
            }
            // 用户发来消息，通知当前连接的客服
            Sender::messageByCustomerServiceId($customer_service_id, [
                'type' => 'message',
                'msg' => '收到消息', 
                'data' => [
                    'message' => $message,
                    'session_id' => $session_id
                ]
            ]);

            Sender::success($this->client_id, [
                'type' => 'receipt',
                'msg' => '发送成功'
            ]);

            if (isset($data['question_id']) && $data['question_id']) {
                Online::questionReply($data['question_id'], [
                    'client_id' => $this->client_id,
                    'session_id' => $session_id,
                    'user_id' => $_SESSION['user_id'],
                    'customer_service_id' => $customer_service_id,
                    'customer_service' => $customerService
                ]);
            }
        } else if ($type == 'message_list') {
            // 获取消息列表
            $linker = [
                'session_id' => $session_id,
                'user_id' => $this->user ? $this->user['id'] : 0
            ];

            // 将客服发给自己的消息标记为 已读
            Online::readMessage($linker, 'customer_service');
            // 通知用户消息列表
            $messageList = Online::messageList($linker, $data);
            Sender::success($this->client_id, [
                'type' => 'message_list',
                'msg' => '获取成功',
                'data' => [
                    'message_list' => $messageList
                ]
            ]);
        }
    }


    /**
     * 连接关闭
     */
    public function close () 
    {
        $customerService = $_SESSION['customer_service'] ?? [];
        $session_id = $_SESSION['uid'];

        if ($customerService) {
            // 存在客服，记录当前服务记录，状态依然还是进行中，
            $userData = [
                'user' => $_SESSION['user'],
                'session_id' => $session_id,
                'chat_user' => $_SESSION['chat_user']
            ];
    
            $connection = Online::checkOrCreateConnection($userData, $customerService);

            // 定时器 10 秒之后 关闭当前用户的所有连接，（规避刷新浏览器问题）
            Timer::add(10, function ($session_id, $customerService) {
                Online::userRealOffline($session_id, $customerService);
            }, [$session_id, $customerService], false);
        }

        // 多端用户，都离线了，才通知客服，用户离线了
        $isUidOnline = Gateway::isUidOnline(online::getUId($session_id, 'user'));
        if (!$isUidOnline) {
            // 通知所有客服，用户下线
            Sender::userOffline($this->session_id);
        }
    }

}