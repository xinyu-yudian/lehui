<?php

namespace app\admin\controller\shopro\commission;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;


/**
 * 分销配置
 *
 * @icon fa fa-circle-o
 */
class Config extends Backend
{

    /**
     * 分销设置模型对象
     * @var \app\admin\model\shopro\commission\Config
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\commission\Config;
    }

   /**
     * 查看
     */
    public function index()
    {
        if($this->request->isAjax()) {
            $data = [];
            if (checkEnv('commission', false)) {
                $data = $this->model->column('name, value');
            }
            
            $this->success('分销设置', null, $data);
        }

        $this->assignconfig('is_upgrade', checkEnv('commission', false) ? false : true);
        
        return $this->view->fetch();
    }

    public function save()
    {
        if ($this->request->isPost()) {
            checkEnv('commission');

            $params = $this->request->post();
            foreach($params as $k => $p) {
                $this->model->where('name', $k)->update([
                    'value' => $p
                ]);
            }
            $this->success('更新成功');
        }
    }

}
