<?php

namespace app\admin\controller\shopro;

use app\common\controller\Backend;

/**
 * 省市区数据
 *
 * @icon fa fa-circle-o
 */
class Area extends Backend
{

    protected $noNeedRight = ['getCascader'];

    /**
     * Area模型对象
     * @var \app\admin\model\shopro\Area
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\Area;

    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    public function getCascader() {
        $area = cache('area-cascader');
        if ($area) {
            $area = json_decode($area, true);
        } else {
            $area = $this->model->with('children.children')->where('level', 1)->order('id asc')->select();
            cache('area-cascader', json_encode($area), 86400);       // 缓存一天
        }

        return $this->success('操作成功', null, $area);
    }

    public function select()
    {
        $ids = $this->request->get();
        $area = cache('area-cascader');
        $area = $this->model->cache(true)->with('children.children')->where('level', 1)->order('id asc')->select();
        if($this->request->isAjax()) {
            $this->success('省市区列表', null, $area);
        }
        $this->assignconfig('areaData', $area);
        return $this->view->fetch();
    }
}
