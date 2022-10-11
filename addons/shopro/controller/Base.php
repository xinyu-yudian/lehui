<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use app\common\controller\Api;
use think\Lang;

class Base extends Api
{
    public function _initialize()
    {

        parent::_initialize();
        $controllername = strtolower($this->request->controller());
        $this->loadlang($controllername);

    }

    protected function loadlang($name)
    {
        Lang::load(ADDON_PATH  . 'shopro/lang/' . $this->request->langset() . '/' . str_replace('.', '/', $name) . '.php');
    }


    protected function shoproValidate($params, $class, $scene, $rules = []) {
        $validate = validate(str_replace('controller', 'validate', $class));
        if (!$validate->check($params, $rules, $scene)) {
            $this->error($validate->getError());
        }
    }
}
