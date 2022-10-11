<?php
/**
 * run with command
 * php start.php start
 */

namespace addons\shopro\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Workerman\Worker;
use addons\shopro\library\chat\Start;

/**
 *
 */
class Chat extends Command
{

    protected function configure()
    {
        $this->setName('shopro:chat')
            ->addArgument('action', Argument::OPTIONAL, "action start [d]|stop|restart|status")
            ->addArgument('type', Argument::OPTIONAL, "d -d")
            ->setHelp('此命令是用来启动 shopro 商城的客服服务端进程')
            ->setDescription('shopro 客服');
    }

    protected function execute(Input $input, Output $output)
    {
        global $argv;
        $action = trim($input->getArgument('action'));
        $type   = trim($input->getArgument('type')) ? '-d' : '';

        $argv[0] = 'shopro:chat';
        $argv[1] = $action;
        $argv[2] = $type ? '-d' : '';
        $this->start($input, $output);
    }

    private function start($input, $output)
    {
        if (strpos(strtolower(PHP_OS), 'win') === 0) {
            $output->writeln("<error>GatewayWorker Not Support On Windows.</error>");
            exit(1);
        }

        // 检查扩展
        if (!extension_loaded('pcntl')) {
            $output->writeln("<error>Please install pcntl extension.</error>");
            exit(1);
        }

        if (!extension_loaded('posix')) {
            $output->writeln("<error>Please install posix extension.</error>");
            exit(1);
        }

        // 实例化启动类
        $startServer = new Start();

        // 启动 register
        $startServer->register();

        // 启动 businessWorker
        $startServer->businessWorker();

        // 启动 gateway
        $startServer->gateway();

        // 设置日志
        $startServer->setLog(__DIR__ . '/../');

        // 运行所有服务
        Worker::runAll();
    }
}
