<?php

return array (
  'type' => 'shopro',
  'basic' => 
  array (
    'last_customer_service' => 1,
    'allocate' => 'busy',
    'notice' => '显示在用户端头部',
  ),
  'system' => 
  array (
    'is_ssl' => 1,
    'ssl_type' => 'cert',
    'ssl_cert' => '/www/server/panel/vhost/cert/****/fullchain.pem',
    'ssl_key' => '/www/server/panel/vhost/cert/****/privkey.pem',
    'gateway_port' => 1819,
    'gateway_num' => 2,
    'gateway_start_port' => 2010,
    'business_worker_port' => 2238,
    'business_worker_num' => 4,
  ),
);