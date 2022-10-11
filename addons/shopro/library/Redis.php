<?php

namespace addons\shopro\library;

use addons\shopro\exception\Exception;

class Redis
{
    protected $handler = null;

    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ];

    /**
     * 构造函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }

        // 获取 redis 配置
        $config = \think\Config::get('redis');
        if (empty($config) && empty($options)) {
            throw new \Exception('redis connection fail: no redis config');
        }

        if (!empty($config)) {
            $this->options = array_merge($this->options, $config);
        }

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->handler = new \Redis();
        if ($this->options['persistent']) {
            $this->handler->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['select']);
        } else {
            $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        }

        if ('' != $this->options['password']) {
            $this->handler->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->handler->select($this->options['select']);
        }

        // 赋值全局，避免多次实例化
        $GLOBALS['SPREDIS'] = $this->handler;
    }

    public function getRedis() {
        return $this->handler;
    }
}
