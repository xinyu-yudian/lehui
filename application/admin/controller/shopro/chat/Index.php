<?php

namespace app\admin\controller\shopro\chat;

use addons\shopro\library\chat\Start;
use addons\shopro\model\chat\ChatUser;
use addons\shopro\model\chat\CustomerService as CS;
use app\admin\controller\shopro\Base;
use app\admin\model\shopro\chat\FastReply;
use Workerman\Worker;

/**
 * 客服初始化
 *
 * @icon fa fa-circle-o
 */
class Index extends Base
{

    protected $startServer = null;
    protected $model = null;
    protected $noNeedLogin = ['businessWorker', 'gateway', 'register'];
    protected $noNeedRight = ['init', 'businessWorker', 'gateway', 'register'];
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\customerservice\CustomerService;
    }
    

    public function businessWorker () {
        $this->startServer = new Start();
        $this->startServer->businessWorker();

        if (!defined('GLOBAL_START')) {
            Worker::runAll();
        }
        exit;
    }


    public function gateway() {
        $this->startServer = new Start();
        $this->startServer->gateway();

        $this->startServer->setLog(APP_PATH . '../addons/shopro/');

        if (!defined('GLOBAL_START')) {
            Worker::runAll();
        }

        exit;
    }


    public function register() {
        $this->startServer = new Start();
        $this->startServer->register();

        if (!defined('GLOBAL_START')) {
            Worker::runAll();
        }

        exit;
    }

    /**
     * 后台客服初始化
     *
     * @return void
     */
    public function init() {
        $admin = $this->auth->getUserInfo();

        if (!$admin) {
            $this->error('您还没有登录，请先登录');
        }

        // 获取管理员对应的客服
        $customerService = CS::where('admin_id', $admin['id'])->find();

        if (!$customerService) {
            $this->error('');
        }

        $config = json_decode(\addons\shopro\model\Config::where(['name' => 'chat'])->value('value'), true);
        $config['type'] = $config['type'] ?? 'shopro';
        $config['system'] = $config['system'] ?? [];
        // 初始化 ssl 类型, 默认 cert
        $config['system']['ssl_type'] = $config['system']['ssl_type'] ?? 'cert';

        if ($config['type'] == 'kefu') {
            $addons = array_keys(get_addon_list());
            if (!in_array('kefu', $addons)) {
                $this->error('请安装 workerman 在线客服插件', null);
            }
        }

        // 返回常用语
        $fastReply = FastReply::show()->order('weigh', 'desc')->select();

        $expire_time = time();
        $result = [
            'token' => md5($admin['username'] . $expire_time),
            'expire_time' => $expire_time,
            'customer_service' => $customerService,
            'config' => $config,
            'fast_reply' => $fastReply,
            'emoji' => json_decode(file_get_contents(ROOT_PATH . 'public/assets/addons/shopro/libs/emoji.json'), true)
        ];

        $this->success('初始化成功', null, $result);
    }
}
