<?php

namespace app\admin\controller\shopro\dispatch;

use app\common\controller\Backend;

/**
 * 配送模板
 *
 * @icon fa fa-circle-o
 */
class Dispatch extends Backend
{
    protected $noNeedRight = ['typeList','all'];
    /**
     * Dispatch模型对象
     * @var \app\admin\model\shopro\dispatch\Dispatch
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\dispatch\Dispatch;
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    /**
     * 获取发货类型
     */
    public function typeList()
    {
        $typeList = $this->model->getTypeList();

        $this->success('获取成功', null, $typeList);
    }


    public function select($type)
    {
        if($this->request->isAjax()) {
            $data = $this->model->where('type', $type)->field('id,name')->select();
            $this->success('模板数据', null, $data);
        }
     
    }

  
}
