<?php

namespace app\admin\controller\shopro\activity;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;
use app\admin\controller\shopro\Base;

use addons\shopro\library\traits\ActivityCache;

/**
 * 营销活动
 *
 * @icon fa fa-circle-o
 */
class Activity extends Base
{
    use ActivityCache;
    /**
     * Activity模型对象
     * @var \app\admin\model\shopro\activity\Activity
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\activity\Activity;
        $this->assignconfig("hasRedis", $this->hasRedis());     // 检测是否配置 redis
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看活动列表
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            // 检测队列
            checkEnv('queue');
            
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $nobuildfields = ['activitytime', 'status'];
            list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(['title'], $nobuildfields);

            $total = $this->buildSearch()
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->buildSearch()
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            // 关联活动的商品
            $goodsIds = array_column($list, 'goods_ids');
            $goodsIdsArr = [];
            foreach($goodsIds as $ids) {
                $idsArr = explode(',', $ids);
                $goodsIdsArr = array_merge($goodsIdsArr, $idsArr);
            }
            $goodsIdsArr = array_values(array_filter(array_unique($goodsIdsArr)));
            if ($goodsIdsArr) {
                // 查询商品
                $goods = \app\admin\model\shopro\goods\Goods::where('id', 'in', $goodsIdsArr)->select();
                $goods = array_column($goods, null, 'id');
            }
            foreach ($list as $key => $activity) {
                $list[$key]['goods'] = [];
                $idsArr = explode(',', $activity['goods_ids']);
                foreach ($idsArr as $id) {
                    if (isset($goods[$id])) {
                        $list[$key]['goods'][] = $goods[$id];
                    }
                }
            }

            $result = array("total" => $total, "rows" => $list);

            if ($this->request->get("page_type") == 'select') {
                return json($result);
            }

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }


    public function all() {
        if ($this->request->isAjax()) {
            $type = $this->request->get('type', 'all');

            $sort = $this->request->get("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
            $order = $this->request->get("order", "DESC");

            $activities = $this->model->withTrashed();               // 包含被删除的
            if ($type != 'all') {
                $activities = $activities->where('type', $type);
            }

            $activities = $activities
                ->field('id, title, type, starttime, endtime, rules')
                ->order($sort, $order)
                ->select();

            $activities = collection($activities)->toArray();

            return $this->success('操作成功', null, $activities);
        }
    }


    // 获取活动的选项
    public function getType()
    {
        $activity_type = (new \app\admin\model\shopro\activity\Activity)->getTypeList();
        $activity_status = (new \app\admin\model\shopro\activity\Activity)->getStatusList();

        $result = [
            'activity_type' => $activity_type,
            'activity_status' => $activity_status,
        ];

        $data = [];
        foreach ($result as $key => $list) {
            $data[$key][] = ['name' => '全部', 'type' => 'all'];

            foreach ($list as $k => $v) {
                $data[$key][] = [
                    'name' => $v,
                    'type' => $k
                ];
            }
        }

        return $this->success('操作成功', null, $data);
    }


    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }

                    // 检测活动是否可以正常添加
                    $this->checkActivity($params);

                    $params['rules'] = json_encode($params['rules']);
                    $result = $this->model->allowField(true)->save($params);

                    if (in_array($params['type'], ['groupon', 'seckill'])) {
                        // 秒杀拼团，更新规格
                        $this->createOrUpdateSku($params['goods_list'], $this->model->id);
                    }

                    // 活动创建修改后
                    $data = [
                        'activity' => $this->model
                    ];
                    \think\Hook::listen('activity_update_after', $data);

                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch('edit');
    }



    /**
     * 添加，编辑活动规格，type = stock 只编辑库存
     *
     * @param array $goodsList  商品列表
     * @param int $activity_id  活动 id
     * @param string $type  type = all 全部编辑，type = stock 只编辑库存
     * @return void
     */
    protected function createOrUpdateSku($goodsList, $activity_id, $type = 'all')
    {
        //如果是编辑 先下架所有的规格产品,防止丢失历史销量数据;

        \app\admin\model\shopro\activity\ActivitySkuPrice::where(['activity_id' => $activity_id])->update(['status' => 'down']);
        $list = [];
        foreach ($goodsList as $k => $g) {
            $actSkuPrice[$k] = json_decode($g['actSkuPrice'], true);

            foreach ($actSkuPrice[$k] as $a => $c) {
                if ($type == 'all') {
                    $current = $c;
                } else {
                    $current = [
                        'id' => $c['id'],
                        'stock' => $c['stock'],
                        'status' => $c['status']
                    ];
                }

                if ($current['id'] == 0) {
                    unset($current['id']);
                }
                unset($current['sales']);
                $current['activity_id'] = $activity_id;
                $current['goods_id'] = $g['id'];
                $list[] = $current;
            }
        }

        $act = new \app\admin\model\shopro\activity\ActivitySkuPrice;
        $act->allowField(true)->saveAll($list);
    }



    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        //编辑
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post();
            if ($params) {
                $params = $this->preExcludeFields($params);

                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }

                    // 检测活动是否可以正常添加
                    $this->checkActivity($params, $row->id);
                    
                    $params['rules'] = json_encode($params['rules']);
                    if ($row['status'] == 'ing') {
                        // 活动正在进行中，只能编辑活动结束时间
                        $params = [
                            'type' => $params['type'],
                            'endtime' => $params['endtime'],
                            'goods_list' => $params['goods_list'],
                        ];
                    }
                    $result = $row->allowField(true)->save($params);

                    if (in_array($params['type'], ['groupon', 'seckill'])) {
                        // 秒杀拼团，更新规格
                        $this->createOrUpdateSku($params['goods_list'], $row->id, ($row['status'] == 'ing' ? 'stock' : 'all'));
                    }

                    // 活动创建修改后
                    $data = [
                        'activity' => $row
                    ];
                    \think\Hook::listen('activity_update_after', $data);

                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $goods_ids_array = array_filter(explode(',', $row->goods_ids));
        $goodsList = [];
        foreach ($goods_ids_array as $k => $g) {
            $goods[$k] = \app\admin\model\shopro\goods\Goods::field('id,title,image')->where('id', $g)->find();
            $goods[$k]['actSkuPrice'] = json_encode(\app\admin\model\shopro\activity\ActivitySkuPrice::all(['goods_id' => $g, 'activity_id' => $ids]));

            $goods[$k]['opt'] = 1;
            $goodsList[] = $goods[$k];
        }

        $row->goods_list = $goodsList;

        $this->assignconfig("activity", $row);
        $this->view->assign("row", $row);
        $this->assignconfig('id', $ids);
        
        return $this->view->fetch();
    }


    /**
     * 选择活动
     */
    public function select()
    {
        if ($this->request->isAjax()) {
            return $this->index();
        }
        return $this->view->fetch();
    }


    
    /**
     * 获取活动规则
     *
     * @param int $id 商品 id
     * @param int $activity_id  活动 id  
     * @param string $type 类型
     * @return void
     */
    public function sku()
    {
        $id = $this->request->get('id', 0);
        $activity_id = $this->request->get('activity_id', 0);
        $activity_type = $this->request->get('activity_type', '');
        $type = $this->request->get('type', 0);
        $activitytime = $this->request->get('activitytime', '') ? $this->request->get('activitytime', '') : '';
        $activitytime = array_filter(explode(' - ', $activitytime));

        if (in_array($type, ['add', 'edit']) && $activitytime && $activity_type) {
            // 如果存在开始结束时间，并且是要修改
            $goodsList = [$id => []];
            try {
                $this->checkGoods($goodsList, [
                    'type' => $activity_type,
                    'starttime' => $activitytime[0],
                    'endtime' => $activitytime[1]
                ], $activity_id);
            } catch(\Exception $e) {
                $this->error(preg_replace('/^部分商品/', '该商品', $e->getMessage()), '');
            }
        }

        // 商品规格
        $skuList = \app\admin\model\shopro\goods\Sku::with(['children' => function ($query) use ($id) {
            $query->where('goods_id', $id);
        }])->where(['pid' => 0, 'goods_id' => $id])->select();

        // 获取规格
        $skuPrice = \app\admin\model\shopro\goods\SkuPrice::with(['activitySkuPrice' => function ($query) use ($activity_id) {
            $query->where('activity_id', $activity_id);
        }])->where(['goods_id' => $id])->select();
        
        //编辑
        $actSkuPrice = [];
        foreach ($skuPrice as $k => &$p) {
            $actSkuPrice[$k] = $p['activity_sku_price'];

            if (!$actSkuPrice[$k]) {

                $actSkuPrice[$k]['id'] = 0;
                $actSkuPrice[$k]['status'] = 'down';
                $actSkuPrice[$k]['price'] = '';
                $actSkuPrice[$k]['stock'] = '';
                $actSkuPrice[$k]['sales'] = '0';
                $actSkuPrice[$k]['sku_price_id'] = $p['id'];

            }
        }

        $this->assignconfig('skuList', $skuList);

        $this->assignconfig('skuPrice', $skuPrice);
        $this->assignconfig('actSkuPrice', $actSkuPrice);

        return $this->view->fetch();

    }


    /**
     * 删除
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();

                    // 删除之后事件
                    $data = [
                        'activity' => $v
                    ];
                    \think\Hook::listen('activity_delete_after', $data);
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }



    // 构建查询条件
    private function buildSearch()
    {
        $filter = $this->request->get("filter", '');
        $filter = (array)json_decode($filter, true);
        $filter = $filter ? $filter : [];

        $status = isset($filter['status']) ? $filter['status'] : 'all';
        $activitytime = isset($filter['activitytime']) ? $filter['activitytime'] : '';
        $activitytime = array_filter(explode(' - ', $activitytime));

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $activities = $this->model;

        // 活动状态
        if ($status != 'all') {
            $where = [];
            if ($status == 'ing') {
                $where['starttime'] = ['<', time()];
                $where['endtime'] = ['>', time()];
            } else if ($status == 'nostart') {
                $where['starttime'] = ['>', time()];
            } else if ($status == 'ended') {
                $where['endtime'] = ['<', time()];
            }

            $activities = $activities->where($where);
        }
        if ($activitytime) {
            $activities = $activities->where('starttime', '>=', strtotime($activitytime[0]))->where('endtime', '<=', strtotime($activitytime[1]));
        }

        return $activities;
    }


    private function checkActivity($params, $activity_id = 0)
    {
        if (empty($params['type'])) {
            throw Exception('请选择活动类型');
        }
        
        if ($params['starttime'] > $params['endtime'] || $params['endtime'] < date('Y-m-d H:i:s')) {
            throw Exception('请设置正确的活动时间');
        }

        if (in_array($params['type'], ['full_reduce', 'full_discount'])) {
            if (!$params['rules'] || !isset($params['rules']['discounts']) || !$params['rules']['discounts']) {
                throw Exception('请设置优惠条件');
            }
        }

        $goodsList = [];
        if ($params['goods_ids']) { // 部分商品
            // 检测要设置商品是否存在活动重合
            foreach ($params['goods_list'] as $key => $goods) {
                if (in_array($params['type'], ['groupon', 'seckill'])) {
                    $actSkuPrice = json_decode($goods['actSkuPrice'], true);
                    if (!$actSkuPrice) {
                        throw Exception('请至少将商品一个规格设置为活动规格');
                    }
                }
                $goodsList[$goods['id']] = $goods;
            }
        }

        // 检测商品是否在别的活动被设置
        $this->checkGoods($goodsList, $params, $activity_id);
    }


    /**
     * 检测活动商品是否重合
     *
     * @return void
     */
    private function checkGoods($goodsList = [], $params, $activity_id = 0)
    {
        $starttime = strtotime($params['starttime']);
        $endtime = strtotime($params['endtime']);
        $goodsIds = array_keys($goodsList);
        // 如果拼团秒杀，当前活动结束时间要包含活动下架时间
        if (in_array($params['type'], ['groupon', 'seckill'])) {
            $current_activity_auto_close = isset($params['rules']['activity_auto_close']) ? intval($params['rules']['activity_auto_close']) : 0;
            $current_activity_auto_close = $current_activity_auto_close > 0 ? ($current_activity_auto_close * 60) : 0;
            $endtime += $current_activity_auto_close;
        }

        // 获取所有活动
        $activities = $this->getActivities($params['type']);

        foreach ($activities as $key => $activity) {
            if ($activity_id && $activity_id == $activity['id']) {
                // 编辑的时候，把自己排除在外
                continue;
            }

            $intersect = [];    // 两个活动重合的商品Ids
            if ($goodsIds) {
                $activityGoodsIds = array_filter(explode(',', $activity['goods_ids']));
                // 不是全部商品，并且不重合
                if ($activityGoodsIds && !$intersect = array_intersect($activityGoodsIds, $goodsIds)) {
                    // 商品不重合，继续验证下个活动
                    continue;
                }
            }

            // 如果活动设置的有活动结束继续显示时间，则检验活动冲突结束时间要加上活动下架时间
            $activity_starttime = $activity['starttime'];
            $activity_endtime = $activity['endtime'];
            if (in_array($activity['type'], ['seckill', 'groupon'])) {
                // 结束时间加上活动自动下架时间
                $activity_auto_close = isset($activity['rules']['activity_auto_close']) ? intval($activity['rules']['activity_auto_close']) : 0;
                $activity_auto_close = $activity_auto_close > 0 ? ($activity_auto_close * 60) : 0;
                $activity_endtime += $activity_auto_close;
            }
            if ($endtime <= $activity_starttime || $starttime >= $activity_endtime) {
                // 设置的时间在当前活动开始之前，或者在当前结束时间之后
                continue;
            }

            $goods_names = '';
            foreach ($intersect as $id) {
                if (isset($goodsList[$id]) && isset($goodsList[$id]['title'])) {
                    $goods_names .= $goodsList[$id]['title'] . ',';
                }
            }

            if ($goods_names) {
                $goods_names = mb_strlen($goods_names) > 40 ? mb_substr($goods_names, 0, 37) . '...' : $goods_names;
            }

            throw Exception('部分商品' . ($goods_names ? ' ' . $goods_names . ' ' : '') . ' 已在 ' . $activity['title'] . ' 活动中设置');
        }
    }



    /**
     * 获取所有活动
     *
     * @return array
     */
    private function getActivities($current_activity_type) {
        // 获取当前活动的互斥活动
        $activityTypes = $this->getMutexActivityType($current_activity_type);

        // 获取所有活动
        if ($this->hasRedis()) {
            // 如果有redis 读取 redis
            $activities = $this->getActivityList($activityTypes, 'all', 'clear');

            return $activities;
        }

        // 没有配置 redis,查询所有活动
        $activities = $this->model->where('type', 'in', $activityTypes)->select();

        return $activities;
    }
    

    /**
     * 获取当前要添加的活动的互斥活动列表
     *
     * @param [type] $current_activity_type
     * @return void
     */
    private function getMutexActivityType($current_activity_type) {
        $activityTypes = [];
        switch($current_activity_type) {
            case 'seckill': 
                // full_reduce full_discount 先不考虑，在获取活动时候，就不会获取这两个了
                $activityTypes = ['seckill', 'groupon'];
                break;
            case 'groupon': 
                // full_reduce full_discount 先不考虑，在获取活动时候，就不会获取这两个了
                $activityTypes = ['seckill', 'groupon'];
                break;
            case 'full_reduce': 
                // seckill groupon 先不考虑，在获取活动时候，如果是拼团秒杀，则full_reduce就不会获取了
                $activityTypes = ['full_reduce', 'full_discount'];
                break;
            case 'full_discount': 
                // seckill groupon 先不考虑，在获取活动时候，如果是拼团秒杀，则full_discount就不会获取了
                $activityTypes = ['full_reduce', 'full_discount'];
                break;
            case 'free_shipping': 
                $activityTypes = ['free_shipping'];
                break;
        }

        return $activityTypes;
    }
}
