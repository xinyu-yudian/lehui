<?php

namespace addons\shopro\library\chat;

use \GatewayWorker\Lib\Gateway;
use addons\shopro\model\chat\Connection;
use addons\shopro\model\chat\CustomerService;
use addons\shopro\model\chat\Log as ChatLog;
use addons\shopro\model\chat\User as ChatUser;
use addons\shopro\library\chat\traits\GetLinkerTrait;
use addons\shopro\model\chat\Question;
use addons\shopro\model\User;
use app\admin\model\Admin;

class Online 
{
    use GetLinkerTrait;


    public static function getConfig($type) {
        // 初始化 workerman 的时候不能读取数据库，会导致数据库连接异常
        $config = require(ROOT_PATH . 'addons' . DS . 'shopro' . DS . 'library' . DS . 'chat' . DS . 'config.php');

        $system = $config[$type] ?? [];
        return $system;
    }



    /**
     * 先判断是否有对象存储，将对象存储配置注入到 upload 配置
     *
     * @return void
     */
    public static function uploadConfigInit() {
        // 获取绑定的 插件 钩子
        $hooks = config('addons.hooks');
        $upload_config_inits = isset($hooks['upload_config_init']) && $hooks['upload_config_init'] ? $hooks['upload_config_init'] : [];
        if ($upload_config_inits) {
            $storage = end($upload_config_inits);
            \think\Hook::add('upload_config_init', 'addons\\' . ucfirst($storage) . '\\' . ucfirst($storage));
            // 注入 storage 配置
            $upload = \app\common\model\Config::upload();
            \think\Hook::listen("upload_config_init", $upload);
            \think\Config::set('upload', array_merge(\think\Config::get('upload'), $upload));
        }
    }

    /**
     * 客服自定义 cdnurl 方法
     * 1、提供默认域名，如果没配置对象存储，拼接当前接口域名（访问域名的直接获取，websocket 的从 websocket SESSION['server']请求头中获取）
     * 2、调用默认 cdnurl 方法
     * 
     * @param [type] $val
     * @param [type] $domain
     * @return void
     */
    public static function cdnurl($val, $domain = null) {
        $domain = $domain ? : self::getDomain();

        return cdnurl($val, $domain);
    }


    /**
     * 获取 websocket 的域名拼接 cdnurl
     *
     * @return void
     */
    public static function getDomain() {
        // 优先以正常访问方式获取域名
        $domain = request()->domain();
        $host = request()->host();
        if (!$domain || !$host) {
            // 如果不能获取，说明是 websocket 连接，获取当前 server 中的 domain
            $server = $_SESSION['server'];
            $systemConfig = self::getConfig('system');
            $is_ssl = $systemConfig['is_ssl'] ? true : false;
            $domain = ($is_ssl ? 'https://' : 'http://') . $server['SERVER_NAME'];
        }

        return $domain;
    }


    // 主要为了记录当前系统总共有多少种分组
    public static function getGrouponName($type, $data = []) {
        switch($type) {
            case 'online_user' :                        // 当前在线用户分组
                $group_name = 'online_user';
                break;
            case 'online_waiting' :                        // 当前在线用户待分配客服分组
                $group_name = 'online_waiting';
                break;
            case 'online_customer_service' :                        // 当前在线客服数组
                $group_name = 'online_customer_service';
                break;
            case 'customer_service_user':               // 当前在线用户所在的客服分组
                $group_name = 'customer_service_user:' . ($data['customer_service_id'] ?? 0);
                break;
            default :
                $group_name = $type;
                break;
        }

        return $group_name;
    }

    public static function getUId($id, $type)
    {
        $ids = is_array($id) ? $id : [$id];
        foreach ($ids as &$i) {
            $i = $type . '-' . $i;
        }

        return is_array($id) ? $ids : $ids[0];
    }


    public static function updateChatUser($session_id, $user) {
        $chatUser = ChatUser::where(function ($query) use ($session_id, $user) {
            $query->where('session_id', $session_id)
                ->whereOr(function ($query) use ($user) {
                    $query->where('user_id', '<>', 0)
                        ->where('user_id', ($user ? $user['id'] : 0));
                });
        })->find();

        $defaultAvatar = null;
        $config = \addons\shopro\model\Config::where('name', 'user')->find();
        if ($config) {
            $userConfig = json_decode($config['value'], true);
            $defaultAvatar = $userConfig['avatar'];
        }

        if (!$chatUser) {
            $chatUser = new ChatUser();
            
            $chatUser->session_id = $session_id;
            $chatUser->user_id = $user ? $user['id'] : 0;
            $chatUser->nickname = $user ? $user['nickname'] : ('游客-' . substr($session_id, 0, 5));
            $chatUser->avatar = $user ? $user->getData('avatar') : $defaultAvatar;
            $chatUser->customer_service_id = 0;      // 断开连接的时候存入
            $chatUser->lasttime = time();
            
        } else {
            if ($user) {
                // 更新用户信息
                $chatUser->user_id = $user['id'] ?? 0;
                $chatUser->nickname = $user['nickname'] ? $user['nickname'] : ('游客-' . substr($session_id, 0, 5));
                $chatUser->avatar = $user['avatar'] ? $user->getData('avatar') : $defaultAvatar;
            }

            $chatUser->lasttime = time();        // 更新时间
        }

        $chatUser->save();
        return $chatUser->toArray();
    }

    
    /**
     * 根据 id 获取客服
     */
    public static function customerServiceById($customer_service_id)
    {
        $customerService = CustomerService::where('id', $customer_service_id)->find();

        return $customerService;
    }



    /**
     * 检查客服身份
     *
     * @param string $token
     * @param string $customer_service_id
     * @param string $expire_time
     * @return array
     */
    public static function checkAdmin($token, $customer_service_id, $expire_time) {
        if ($token && $customer_service_id) {
            // 获取客服信息
            $customerService = self::customerServiceById($customer_service_id);
            if (!$customerService) {
                return false;
            }
            $customerService = $customerService->toArray();

            // 获取 admin
            $admin = Admin::get($customerService['admin_id']);
            if (!$admin) {
                return false;
            }
            $admin = $admin->toArray();

            $current_token = md5($admin['username'] . $expire_time);
            if (($expire_time + (86400 * 30)) > time() && $current_token == $token) {
                // 验证通过
                return [
                    'customerService' => $customerService,
                    'admin' => $admin
                ];
            }
        }

        return false;
    }



    /**
     * 检测并获取用户
     *
     * @param [type] $token
     * @return void
     */
    public static function checkUser($token) {
        $data = \app\common\library\Token::get($token);

        if (!$data) {
            return false;
        }

        $user_id = intval($data['user_id']);
        if ($user_id <= 0) {
            return false;
        }
        
        // 这个 user 不能转数组，用到了 $user->getData('avatar') 去拿原始的未拼接 cdnurl 头像地址
        $user = User::where('id', $user_id)->find();
        if (!$user) {
            return false;
        } 

        return $user;
    }



    /**
     * 判断客服在线状态, 只能客服用
     *
     * @param [type] $customer_service_id
     * @return void
     */
    public static function customerServiceStatusById($customer_service_id, $oper_type = 'customer_service', $data = [])
    {
        $current_client_id = $data['client_id'] ?? '';      // oper_type = customer_service 时存在
        
        // 获取当前用户的所有session,并且当前连接的session是最新的
        $customerServiceSessions = self::onlineCustomerServiceNewSessionById($customer_service_id, $current_client_id);
        
        $status = false;
        foreach ($customerServiceSessions as $key => $customerServiceSession) {
            $customerService = $customerServiceSession['user'] ?? [];
            if ($customerService && in_array($customerService['status'], ['online', 'busy'])) {
                // 只要其中一个在线，即为在线
                $status = true;
            }
        }

        return $status;
    }



    /**
     * 客服上线
     *
     * @param string $client_id
     * @param object $customerService
     * @return object
     */
    public static function customerServiceOnline($client_id, $customerService)
    {
        // 更新客服状态
        CustomerService::where('id', $customerService['id'])->update([
            'status' => 'online'
        ]);

        // 重新更新客服信息
        $customerService['status'] = 'online';

        // 更新客服 session 为 online
        $_SESSION['user']['status'] = 'online';

        // 加入在线客服组
        Gateway::joinGroup($client_id, Online::getGrouponName('online_customer_service'));

        // 通知客服连接成功
        Sender::customerServiceInit($client_id, $customerService);

        // 通知连接的用户，客服上线了
        Sender::customerServiceOnline($customerService);

        // 通知所有在线客服，更新当前在线客服列表
        Sender::customerServiceOnlineList();

        return $customerService;
    }


    /**
     * 客服忙碌
     *
     * @param string $client_id
     * @param object $customerService
     * @return object
     */
    public static function customerServiceBusy($client_id, $customerService)
    {
        // 更新客服状态
        CustomerService::where('id', $customerService['id'])->update([
            'status' => 'busy'
        ]);

        // 重新更新客服信息
        $customerService['status'] = 'busy';

        // 更新客服 session 为 busy
        $_SESSION['user']['status'] = 'busy';

        return $customerService;
    }


    /**
     * 客服下线（这里不更新数据库,并且只更新当前连接为下线，如果当前客服在别的浏览器也有登录，则不受此操作影响）
     *
     * @param string $client_id
     * @param object $customerService
     * @return object
     */
    public static function customerServiceOffline($client_id = null, $customerService)
    {
        // 重新更新客服信息
        $customerService['status'] = 'offline';

        // 更新客服 session 为 offline
        $_SESSION['user']['status'] = 'offline';

        // 移除在线客服
        Gateway::leaveGroup($client_id, Online::getGrouponName('online_customer_service'));

        // 获取客服在线状态
        $onlineStatus = self::customerServiceStatusById($customerService['id'], 'customer_service', ['client_id' => $client_id]);
        
        if (!$onlineStatus) {
            // 绑定该 uid 的所有 session 的客服状态都离线了或者客服真实离线了，通知用户客服下线
            Sender::customerServiceOffline($customerService);
        }

        // 通知所有客服更新在线的客服列表
        Sender::customerServiceOnlineList();

        return $customerService;
    }



    /**
     * 客服真实离线
     *
     * @param int $customer_service_id
     * @param array $customerService
     * @return void
     */
    public static function customerServiceRealOffline($customer_service_id, $customerService) {
        $isUidOnline = Gateway::isUidOnline(online::getUId($customer_service_id, 'customer_service'));

        if (!$isUidOnline) {
            // 绑定该 uid 的所有 client_id 都离线了，更新客服数据库为离线状态
            CustomerService::where('id', $customerService['id'])->update([
                'status' => 'offline'
            ]);
        }
    }


    /**
     * 更新客服最后接入时间
     *
     * @param [type] $customerService
     * @param [type] $oper_type
     * @return void
     */
    public static function updateCustomerServiceLasttime($customerService, $oper_type)
    {
        // 更新 session 的 lasttime
        if ($oper_type == 'customer_service') {
            // 如果是客服自己操作接入
            $_SESSION['user']['lasttime'] = time();
        } else {
            // 获取客服 client_id;
            $customerServiceClientIds = Gateway::getClientIdByUid(Online::getUId($customerService['id'], 'customer_service'));

            foreach ($customerServiceClientIds as $customer_service_client_id) {
                $session = Gateway::getSession($customer_service_client_id);
                $customerService = $session['user'];        // 客服信息
                $customerService['lasttime'] = time();      // 更新最后接入时间
    
                // 更新 session 缓存
                Gateway::updateSession($customer_service_client_id, [
                    'user' => $customerService,
                ]);
            }
        }

        // 更新数据库
        CustomerService::where('id', $customerService['id'])->update([
            'lasttime' => time()
        ]);
    }



    /**
     * 用户最后一次的链接
     *
     * @param integer $user_id
     * @param string $session_id
     * @return array
     */
    public static function userLastConnection($user_id = 0, $session_id = '') {
        $lastConnection = Connection::where(function ($query) use ($user_id, $session_id) {
            if ($session_id) {
                $query->where(function ($query) use ($session_id) {
                    $query->where('session_id', '<>', '')
                        ->where('session_id', 'not null')
                        ->where('session_id', $session_id);
                });
            }
            if ($user_id) {
                $query->where(function ($query) use ($user_id) {
                    $query->where('user_id', '<>', 0)
                        ->where('user_id', 'not null')
                        ->where('user_id', $user_id);
                });
            }
        })->where('customer_service_id', '<>', 0)->order('id', 'desc')->find();

        return $lastConnection;
    }



    /**
     * 关闭当前 session_id 的所有连接
     */
    public static function closeConnectionBySessionId($session_id)
    {
        Connection::where('session_id', $session_id)->where('status', 'in', ['ing', 'waiting'])->update([
            'status' => 'end'
        ]);
    }


    /**
     * 分配客服
     *
     * @param string $session_id
     * @param string $user_id
     * @return array
     */
    public static function allocatCustomerService($session_id, $user_id) {
        $config = self::getConfig('basic');
        $last_customer_service = $config['last_customer_service'] ?? 1;
        $allocate = $config['allocate'] ?? 'busy';

        // 分配的客服
        $currentCustomerService = null;

        // 使用上次客服
        if ($last_customer_service) {
            // 获取上次连接的信息
            $lastConnection = self::userLastConnection($user_id, $session_id);

            $lastCustomerService = null;
            $currentCustomerService = null;
            if ($lastConnection) {
                // 获取上次客服信息
                // $lastCustomerService = Online::customerServiceById($lastConnection['customer_service_id']);          // 读取数据库，不准确
                $lastCustomerService = Self::onlineCustomerServiceById($lastConnection['customer_service_id']);       // 获取socket 连接里面上次客服是否在线

                // 判断客服是否在线
                if ($lastCustomerService) {
                    if ($lastCustomerService['status'] == 'online') {
                        $currentCustomerService = $lastCustomerService;
                    }
                }
            }
        }
        
        // 没有客服，随机分配
        if (!$currentCustomerService) {
            // 在线客服列表
            $onlineCustomerServices = self::onlineCustomerServices();

            if ($onlineCustomerServices) {
                if ($allocate == 'busy') {
                    // 将客服列表，按照工作繁忙程度正序排序, 这里没有离线的客服
                    $onlineCustomerServices = array_column($onlineCustomerServices, null, 'busy_percent');
                    ksort($onlineCustomerServices);
                    
                    // 取忙碌度最小，并且客服为 正常在线状态
                    foreach ($onlineCustomerServices as $customerService) {
                        if ($customerService['status'] == 'online') {
                            $currentCustomerService = $customerService;
                            break;
                        }
                    }

                    if (!$currentCustomerService) {
                        // 如果都不是 online 状态，默认取第一条
                        $currentCustomerService = $onlineCustomerServices[0] ?? null;
                    }
                } else if ($allocate == 'turns') {
                    // 按照最后接入时间正序排序，这里没有离线的客服
                    $onlineCustomerServices = array_column($onlineCustomerServices, null, 'lasttime');
                    ksort($onlineCustomerServices);

                    // 取忙碌度最小，并且客服为 正常在线状态
                    foreach ($onlineCustomerServices as $customerService) {
                        if ($customerService['status'] == 'online') {
                            $currentCustomerService = $customerService;
                            break;
                        }
                    }

                    if (!$currentCustomerService) {
                        // 如果都不是 online 状态，默认取第一条
                        $currentCustomerService = $onlineCustomerServices[0] ?? null;
                    }
                } else if ($allocate == 'random') {
                    // 随机获取一名客服
                    $onlineCustomerServices = array_column($onlineCustomerServices, null, 'id');

                    $customer_service_id = 0;
                    if ($onlineCustomerServices) {
                        $customer_service_id = array_rand($onlineCustomerServices);
                    }

                    $currentCustomerService = $onlineCustomerServices[$customer_service_id] ?? null;
                }
            }
        }

        return $currentCustomerService;
    }


    /**
     * 通过 session_id 记录客服信息，并且加入对应的客服组
     *
     * @param string $session_id    用户
     * @param array $customerService    客服信息
     * @return null
     */
    public static function bindCustomerServiceBySessionId($session_id, $customerService, $oper_type = 'customer_service') {
        $uid = Online::getUId($session_id, 'user');
        $client_ids = Gateway::getClientIdByUid($uid);

        // 将当前 session_id 绑定的 client_id 都加入当前客服组
        foreach ($client_ids as $client_id) {
            self::bindCustomerService($client_id, $customerService, $oper_type);
        }
    }


    /**
     * 记录客服信息，并且加入对应的客服组
     *
     * @param string $client_id    用户
     * @param array $customerService    客服信息
     * @return null
     */
    public static function bindCustomerService($client_id, $customerService, $oper_type = 'user') {
        // 当前用户使用 updateSession 会吧之前的内容覆盖掉，并且 getSession 无法获取刚刚设置的 session

        // 更新用户的客服信息
        if ($oper_type == 'user') {
            // 当前连接者
            $_SESSION['customer_service_id'] = $customerService['id'];
            $_SESSION['customer_service'] = $customerService;
        } else {
            // 其他连接着
            Gateway::updateSession($client_id, [
                'customer_service_id' => $customerService['id'],
                'customer_service' => $customerService
            ]);
        }
        
        // 更新客服的最后接入用户时间
        self::updateCustomerServiceLasttime($customerService, $oper_type);

        // 加入对应客服组，统计客服信息 customer_service_user:客服 ID
        Gateway::joinGroup($client_id, Online::getGrouponName('customer_service_user', ['customer_service_id' => $customerService['id']]));
        // 从等待接入组移除
        Gateway::leaveGroup($client_id, Online::getGrouponName('online_waiting'));
    }



    /**
     * 通过 session_id 将用户移除客服组
     *
     * @param string $session_id    用户
     * @param array $customerService    客服信息
     * @return null
     */
    public static function unBindCustomerServiceBySessionId($session_id, $customerService, $oper_type = 'customer_service')
    {
        $uid = Online::getUId($session_id, 'user');
        $client_ids = Gateway::getClientIdByUid($uid);

        // 将当前 session_id 绑定的 client_id 都移除当前客服组
        foreach ($client_ids as $client_id) {
            // 这里新客服已经绑定了，不需要移除session 了，所以 remove_session 为 false
            self::unBindCustomerService($client_id, $customerService, false, $oper_type);
        }
    }


    /**
     * 将用户的客服信息移除
     *
     * @param string $client_id    用户
     * @param array $customerService    客服信息
     * @return null
     */
    public static function unBindCustomerService($client_id, $customerService, $remove_session = false, $oper_type = false)
    {
        if ($remove_session) {
            if ($oper_type == 'user') {
                // 当前连接者
                $_SESSION['customer_service_id'] = 0;
                $_SESSION['customer_service'] = [];
            } else {
                // 其他连接着
                Gateway::updateSession($client_id, [
                    'customer_service_id' => 0,
                    'customer_service' => []
                ]);
            }
        }

        // 移除对应客服组
        Gateway::leaveGroup($client_id, Online::getGrouponName('customer_service_user', ['customer_service_id' => $customerService['id']]));
    }


    // 转接用户
    public static function transferCustomerServiceBySessionId($session_id, $newCustomerService, $oldCustomerService, $oper_type = 'customer_service') {
        // 将老客服的服务记录直接保存
        $chatUser = Self::getChatUserBySessionId($session_id);
        if ($chatUser && $chatUser->user_id) {
            $user = User::get($chatUser->user_id);
        }
        $userData = [
            'user' => $user ?? null,
            'session_id' => $session_id,
            'chat_user' => $chatUser
        ];

        // 创建并结束 connection
        $connection = Online::checkOrCreateConnection($userData, $oldCustomerService, true);

        // 新客服接入用户
        self::bindCustomerServiceBySessionId($session_id, $newCustomerService, $oper_type);

        // 将用户从旧的客服组移除
        self::unBindCustomerServiceBySessionId($session_id, $oldCustomerService, $oper_type);
    }


    /**
     * 排队的用户，将用户加入 waiting 组
     *
     * @param string $client_id
     * @return null
     */
    public static function bindWaiting ($client_id) {
        Gateway::joinGroup($client_id, Online::getGrouponName('online_waiting'));
    }


    /**
     * 判断是否有链接，如果没有创建新链接（为了避免用户刷新，然后这里重新创建新纪录）
     */
    public static function checkOrCreateConnection($userData, $customerService, $set_end = false) {
        $user = $userData['user'] ?? null;
        $chatUser = $userData['chat_user'] ?? null;
        $session_id = $userData['session_id'] ?? '';

        // 正在进行中的连接
        $ingConnection = Connection::where('customer_service_id', $customerService['id'])
                ->where('status', 'in', ['ing', 'waiting'])
                ->where(function ($query) use ($user, $session_id) {
                    $query->where('session_id', $session_id)->whereOr('user_id', ($user->id ?? 0));
                })->find();
        
        if (!$ingConnection) {
            // 不存在，创建新的
            $ingConnection = new Connection();
    
            $ingConnection->user_id = $user ? $user['id'] : 0;
            $ingConnection->nickname = $chatUser['nickname'];
            $ingConnection->session_id = $session_id;
            $ingConnection->customer_service_id = $customerService ? $customerService['id'] : 0;        // 0 没有客服在;
            $ingConnection->starttime = time();
            $ingConnection->endtime = $set_end ? time() : 0;
            $ingConnection->status = $set_end ? 'end' : ($customerService ? 'ing' : 'waiting');
            $ingConnection->createtime = time();
            $ingConnection->updatetime = time();
            $ingConnection->save();
        } else {
            if ($ingConnection->status == 'waiting' && $customerService) {
                // 如果是 waiting, 并且存在客服，修改为 ing
                $ingConnection->customer_service_id = $customerService['id'];
                $ingConnection->status = 'ing';
            }

            if ($set_end) {
                $ingConnection->status = 'end';
                $ingConnection->endtime = time();
            }

            $ingConnection->save();
        }

        return $ingConnection;
    }


    /**
     * 用户登录，更新当前 session_id 没有 user_id 的记录
     */
    public static function sessionUserSave($user, $session_id) {
        // 更新用户连接
        $connection = Connection::where('session_id', $session_id)->where('user_id', 0)->update([
            'user_id' => $user['id'],
            'nickname' => $user['nickname']
        ]);

        // 更新用户消息记录
        $connection = ChatLog::where('session_id', $session_id)->where('user_id', 0)->update([
            'user_id' => $user['id']
        ]);

        return true;
    }


    /**
     * 通过连接 id 获取连接
     */
    public static function connectionById($connection_id = 0) {
        $connection = Connection::where('id', $connection_id)->find();

        return $connection;
    }


    /**
     * 通过客服 id 获取当前客服正在服务的客户
     */
    public static function onlineByCustomerServiceId($customerService) {
        $onlines = Connection::where('status', 'ing')->where('customer_service_id', $customerService['id'])->select();

        return $onlines;
    }


    // 通过客服 id 获取当前客服历史服务过的客户
    public static function historyByCustomerServiceId($customerService, $data)
    {
        $except = $data['except'] ?? [];

        // 关闭 sql mode 的 ONLY_FULL_GROUP_BY
        $oldModes = closeStrict(['ONLY_FULL_GROUP_BY']);

        $historieLogs = Connection::with(['chat_user'])
                ->where('customer_service_id', $customerService['id'])
                ->where('session_id', 'not in', $except)
                ->group('session_id')
                ->select();

        // 恢复 sql mode
        recoverStrict($oldModes);

        $histories = array_column($historieLogs, 'chat_user');

        return array_values(array_filter($histories));
    }

    // 获取当前所有正在排队的用户
    public static function waiting()
    {
        $waiting = Connection::where('status', 'waiting')->select();

        return $waiting;
    }


    /**
     * 通过 session_id 删除客户服务记录
     *
     * @param array $customerService
     * @param string $session_id
     * @return void
     */
    public static function delUserBySessionId($customerService, $session_id) {
        return Connection::where('customer_service_id', $customerService['id'])->where('session_id', $session_id)->delete();
    }


    /**
     * 删除所有客户服务记录
     *
     * @param array $customerService
     * @param string $session_id
     * @return void
     */
    public static function delAllUserBySessionId($customerService)
    {
        return Connection::where('customer_service_id', $customerService['id'])->delete();
    }




    /**
     * 发送消息
     *
     * @param string $name  要调用的方法名，这里未使用
     * @param string $arguments 给要调用的方法的参数
     * @return object
     */
    public static function addMessage($name, $arguments) {
        $receive_id = $arguments[0] ?? 0;
        $content = $arguments[1] ?? [];
        $params = $arguments[2] ?? [];      // 额外参数
        $sender = $params['sender'] ?? [];

        // 判断是否传入的发送人
        if ($sender) {
            // 传入了发送人信息
            extract($sender);
        } else {
            // 默认对发 用户 发送给客服， 客服发送给用户
            $sender_identify = $_SESSION['identify'];

            if ($sender_identify == 'customer_service') {
                // 获取发送信息
                extract(self::getCustomerServiceSenderData($receive_id, $content));
            } else {
                // 用户
                $session_id = $_SESSION['uid'];
                $user_id = $_SESSION['user_id'] ?? 0;       // 如果是游客，这里为 0
                $user = $_SESSION['user'];
                $sender_id = $_SESSION['chat_user'] ? $_SESSION['chat_user']['id'] : 0;
            }
        }

        // 返回 message_type message
        extract(self::getMessageData($content));

        $chatLog = new ChatLog();

        $chatLog->session_id = $session_id;
        $chatLog->user_id = $user_id;
        $chatLog->sender_identify = $sender_identify;
        $chatLog->sender_id = $sender_id;
        $chatLog->message_type = $message_type;
        $chatLog->message = $message;
        $chatLog->save();

        // 加载消息人
        $chatLog->identify = $chatLog->identify;

        return $chatLog;
    }



    /**
     * 客服给用户发消息是，获取发送参数
     *
     * @param [type] $receive_id
     * @param [type] $content
     * @return array
     */
    public static function getCustomerServiceSenderData($receive_id, $content) {
        // 客服
        $sender_id = $customer_service_id = $_SESSION['uid'];
        $customerService = $_SESSION['user'];
        $session_id = $receive_id;

        $user_id = 0;
        $uid = Online::getUId($session_id, 'user');
        if (Gateway::isUidOnline($uid)) {
            // 接受者在线, 通过 uid 获取 client_id, 返回的是一个数组，取第一个，只是为了取对应的 user_id
            $client_ids = Gateway::getClientIdByUid($uid);

            // 获取数组第一个
            $client_ids = array_values(array_filter($client_ids));
            $client_id = $client_ids[0] ?? 0;

            $receiveSession = Gateway::getSession($client_id);
            if ($receiveSession && $receiveSession['user_id']) {
                $user_id = $receiveSession['user_id'];
            }
        } else {
            // 通过 chatUser 获取 user_id
            $chatUser = self::getChatUserBySessionId($session_id);
            if ($chatUser) {
                $user_id = $chatUser['user_id'];
            }
        }

        return compact("session_id", "user_id", "sender_id");
    }


    
    /**
     * 获取消息类型
     *
     * @param [type] $content
     * @return array
     */
    public static function getMessageData ($content) {
        $type = $content['type'] ?? '';
        $msg = $content['msg'] ?? '';
        $data = $content['data'] ?? [];         // 特定 type 类型存在，包含 type = message
        $messageData = $data['message'] ?? [];      // type=message 存在 

        if (in_array($type, ['init', 'access'])) {
            // 系统消息
            $message_type = 'system';
            $message = $msg;
        } else if ($type == 'message') {
            $message_type = $messageData['message_type'] ?? 'text';
            $message_content = $messageData['message'] ?? '';

            switch ($message_type) {
                case 'text':
                    $message = $message_content;
                    break;
                default:
                    $message = is_array($message_content) ? json_encode($message_content) : $message_content;
                    break;
            }
        }

        return compact("message_type", "message");
    }

    /**
     * 将消息标记为已读
     *
     * @param array $linker 用户信息，user_id session_id
     * @param string $select_identify user & customer_service
     * @return void 
     */
    public static function readMessage($linker, $select_identify = 'user')
    {
        $session_id = $linker['session_id'] ?? '';
        $user_id = $linker['user_id'] ?? '';
        $select_identify = camelize($select_identify);      // 处理为 驼峰

        ChatLog::where(function ($query) use ($session_id, $user_id) {
            $query->where(function ($query) use ($session_id) {
                $query->where('session_id', $session_id)
                    ->where('session_id', 'not null')
                    ->where('session_id', '<>', '');
            })
            ->whereOr(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                    ->where('user_id', '<>', 0);
            });
        })->{$select_identify}()->update([
            'readtime' => time()
        ]);
    }



    /**
     * 获取消息列表
     *
     * @param array $linker     要获取的用户
     * @param array $data       参数
     * @return array
     */
    public static function messageList($linker, $data) {
        $session_id = $linker['session_id'] ?? '';
        $user_id = $linker['user_id'] ?? '';

        $page = $data['page'] ?? 1;
        $per_page = $data['per_page'] ?? 20;
        $last_id = $data['last_id'] ?? 0;

        $messageList = ChatLog::where(function ($query) use ($session_id, $user_id) {
            $query->where(function ($query) use ($session_id) {
                $query->where('session_id', $session_id)
                    ->where('session_id', 'not null')
                    ->where('session_id', '<>', '');
            })
            ->whereOr(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                    ->where('user_id', '<>', 0);
            });
        });

        // 避免消息重复
        if ($last_id) {
            $messageList = $messageList->where('id', '<=', $last_id);
        }

        $messageList = $messageList->order('id', 'desc')->paginate($per_page, false, [
            'page' => $page
        ]);

        $messageData = $messageList->items();
        if ($messageData) {
            // 批量处理消息发送人
            $messageData = ChatLog::formatIdentify($messageData);
            $messageList->data = $messageData;
        }

        return $messageList;
    }



    /**
     * 用户列表增加最后一条聊天记录
     * @param array $chatUsers
     * @param string $select_identify 查询对象，默认客服查用户的，用户查客服的
     * @return array
     */
    public static function userSetMessage($chatUsers, $select_identify = 'user') {
        foreach ($chatUsers as &$chatUser) {
            $chatUser['last_message'] = ChatLog::where('session_id', $chatUser['session_id'])->order('id', 'desc')->find();
            $chatUser['message_unread_num'] = ChatLog::where('session_id', $chatUser['session_id'])->where('readtime', 'null')->{$select_identify}()->count();
        }

        return $chatUsers;
    }


    public static function userRealOffline($session_id, $customerService) {
        // 判断 uid 是否还在线，如果是刷新浏览器，只会出现很短暂的掉线，这里检测依然还是在线状态
        $isUidOnline = Gateway::isUidOnline(online::getUId($session_id, 'user'));
        if (!$isUidOnline) {
            // 不在线，关闭这个用户所有连接
            self::closeConnectionBySessionId($session_id);

            // 记录用户本次客服人员，并且更新最后服务时间
            $chatUser = self::getChatUserBySessionId($session_id);
            $chatUser->customer_service_id = $customerService['id'] ?? 0;
            $chatUser->lasttime = time();
            $chatUser->save();
        }
    }


    /**
     * 常见问题
     *
     * @param int $question_id
     * @param array $receives
     * @return void
     */
    public static function questionReply ($question_id, $receives) {
        $question = Question::get($question_id);

        if ($question) {
            Sender::questionReply($question, $receives);
        }
    }
}