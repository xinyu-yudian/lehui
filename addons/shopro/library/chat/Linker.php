<?php

namespace addons\shopro\library\chat;


class Linker
{
    public $data = [];

    public $linker = null;

    public function __construct($client_id, $data)
    {
        $this->data = $data;

        $identify = $data['identify'];
        
        $identify = "\\addons\\shopro\\library\\chat\\linker\\" . ucfirst(camelize($identify));
        if (!class_exists($identify)) {
            // 连接身份不存在

        }

        $this->linker = new $identify($this, $client_id, $data);
    }



    public function linker() {
        return $this->linker;
    }


    // 代理身份相关的方法
    public function __call($method, $parameters)
    {
        return $this->linker()->{$method}(...$parameters);
    }
}
