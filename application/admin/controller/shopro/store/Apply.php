<?php

namespace app\admin\controller\shopro\store;

use app\common\controller\Backend;
use app\admin\model\shopro\store\Store as StoreModel;

/**
 * 门店
 *
 * @icon fa fa-circle-o
 */
class Apply extends Backend
{
    
    /**
     * Apply模型对象
     * @var \app\admin\model\shopro\store\Apply
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\store\Apply;
        $this->view->assign("openweeksList", $this->model->getOpenweeksList());
        $this->view->assign("statusList", $this->model->getStatusList());
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
                    ->with(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','name','user_id','user','images','realname','phone','province_name','city_name','area_name','province_id','city_id','area_id','address','latitude','longitude','openhours','openweeks','apply_num','status','status_msg','createtime','updatetime']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }

    public function detail($ids = null)
    {
        $row = $this->model->with('user')->where('id', 'in', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        
        $this->assignconfig("row", $row);
        $this->view->assign("row", $row);
        
        return $this->view->fetch();
    }


    /**
     * 审核操作
     *
     * @param [type] $id
     * @return void
     */
    public function applyOper($id)
    {
        $status = $this->request->post('status', -1);
        $status_msg = $this->request->post('status_msg', '');

        $apply = $this->model->get($id);

        if ($apply->status != 0) {
            $this->error('该申请已处理，不能重复处理');
        }

        $result = \think\Db::transaction(function () use ($apply, $status, $status_msg) {
            $apply->status = $status;
            $apply->status_msg = $status_msg;
            $apply->save();

            if ($status == 1) {
                // 把门店申请信息同步到门店
                $store = new StoreModel();
                $store->name = $apply->name;
                $store->images = $apply->images;
                $store->realname = $apply->realname;
                $store->phone = $apply->phone;
                $store->province_name = $apply->province_name;
                $store->city_name = $apply->city_name;
                $store->area_name = $apply->area_name;
                $store->province_id = $apply->province_id;
                $store->city_id = $apply->city_id;
                $store->area_id = $apply->area_id;
                $store->address = $apply->address;
                $store->latitude = $apply->latitude;
                $store->longitude = $apply->longitude;
                $store->service_type = 'radius';
                $store->service_radius = 1000;
                $store->openhours = $apply->openhours;
                $store->openweeks = $apply->openweeks;
                $store->status = $apply->status;
                $store->save();

                // 添加门店管理员
                $userStore = \think\Db::name('shopro_user_store')->insert([
                    'user_id' => $apply->user_id,
                    'store_id' => $store->id
                ]);
            }

            return [
                'apply' => $apply,
                'store' => $store ?? null
            ];
        });

        // 门店审核结果通知
        $user = \addons\shopro\model\User::where('id', $result['apply']['user_id'])->find();
        $user && $user->notify(
            new \addons\shopro\notifications\store\Apply([
                'apply' => $result['apply'],
                'store' => $result['store'],
                'event' => 'store_apply'
            ])
        );


        return $this->success('操作成功', null, $result);
    }
}
