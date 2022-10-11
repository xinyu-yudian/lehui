<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

return [
    'connector' => 'redis',         // 队列驱动使用 redis 推荐， 可选 database
    'host' => \think\Env::get('redis.host', '127.0.0.1'),              // redis 主机地址
    'password' => \think\Env::get('redis.password', ''),                   // redis 密码
    'port' => \think\Env::get('redis.port', '6379'),                     // redis 端口
    'select' => \think\Env::get('redis.select', 1),                      // redis db 库, 建议显示指定 1-15 的数字均可，如果缓存驱动是 redis，避免和缓存驱动 select 冲突
    'timeout' => \think\Env::get('redis.timeout', 0),                     // redis 超时时间
    'persistent' => \think\Env::get('redis.persistent', false),              // redis 持续性，连接复用
];
