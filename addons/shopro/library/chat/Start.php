<?php

namespace addons\shopro\library\chat;

use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use Workerman\Worker;

/**
 * 启动 gateway
 */
class Start
{
    public $config = null;

    public function __construct() {
        $this->config = Online::getConfig('system');
    }


    // 启动 register
    public function register () {
        $register = new Register('text://0.0.0.0:' . $this->config['business_worker_port']);
    }


    // 启动 businessWorker
    public function businessWorker() {
        // bussinessWorker 进程
        $worker = new BusinessWorker();
        // worker名称
        $worker->name = 'ShoproChatBusinessWorker';
        // bussinessWorker进程数量
        $worker->count = $this->config['business_worker_num'];
        // 服务注册地址
        $worker->registerAddress = '127.0.0.1:' . $this->config['business_worker_port'];
        //设置Event 类
        $worker->eventHandler = 'addons\shopro\library\chat\Events';
    }



    // 启动 gateway
    public function gateway() {
        $is_ssl = $this->config['is_ssl'] ?? 0;
        $ssl_type = $this->config['ssl_type'] ?? 'cert';
        $ssl_cert = $this->config['ssl_cert'] ?? '';
        $ssl_key = $this->config['ssl_key'] ?? '';

        $context = [];
        if ($is_ssl && $ssl_type == 'cert') {
            // is_ssl 并且是证书模式
            $context['ssl'] = [
                'local_cert' => $ssl_cert,
                'local_pk' => $ssl_key,
                'verify_peer' => false
            ];
        }

        // gateway 进程，这里使用Text协议，可以用telnet测试
        $gateway = new Gateway("websocket://0.0.0.0:" . $this->config['gateway_port'], $context);

        if ($is_ssl && $ssl_type == 'cert') {
            // 开启 ssl 传输
            $gateway->transport = 'ssl';
        }

        // gateway名称，status方便查看
        $gateway->name = 'ShoproChatGateway';
        // gateway进程数
        $gateway->count = $this->config['gateway_num'];
        // 本机ip，分布式部署时使用内网ip
        $gateway->lanIp = '127.0.0.1';
        // 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
        // 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口 
        $gateway->startPort = $this->config['gateway_start_port'];
        // 服务注册地址
        $gateway->registerAddress = '127.0.0.1:' . $this->config['business_worker_port'];

        // 心跳间隔
        $gateway->pingInterval = 30;
        // 心跳数据
        $gateway->pingData = '';        // 客户端定时发送心跳
        // 客户端在30秒内有1次未回复就断开连接
        $gateway->pingNotResponseLimit = 3;
    }



    // 设置日志
    public function setLog($basePath) {
        // 日志文件
        Worker::$logFile = $basePath . 'library/chat/log/shopro_chat.log';
        // Worker::$stdoutFile = $basePath . 'library/chat/log/std_out.log';        // 如果部署的时候部署错误（比如未删除php禁用函数），会产生大量日志，先关掉
        Worker::$pidFile = $basePath . 'library/chat/log/shopro_chat.pid';
    }
}
