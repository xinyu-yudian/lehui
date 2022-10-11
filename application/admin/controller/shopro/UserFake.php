<?php

namespace app\admin\controller\shopro;

use app\common\controller\Backend;
use think\Db;
/**
 * 虚拟用户
 *
 * @icon fa fa-circle-o
 */
class UserFake extends Backend
{
    
    /**
     * UserFake模型对象
     * @var \app\admin\model\shopro\UserFake
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\UserFake;

    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','nickname','avatar']);
                
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     *  获取随机虚拟用户
     */
    public function random_user()
    {
        $userFake = $this->model->orderRaw('rand()')->find();
        if ($userFake) {
            $result = [
                'code' => 1,
                'data' => $userFake,
                'msg' => ''
            ];
        }else{
            $result = [
                'code' => 0,
                'data' => null,
                'msg' => '资料管理中添加虚拟用户'
            ];

        }
        return json($result);

    }

}
