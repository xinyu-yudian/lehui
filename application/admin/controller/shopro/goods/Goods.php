<?php

namespace app\admin\controller\shopro\goods;

use app\admin\controller\shopro\Config;
use app\admin\model\shopro\activity\Activity;
use app\common\controller\Backend;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Env;
use think\Log;
use think\Response;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;
use addons\shopro\library\traits\StockWarning;
use xcxCode\XcxCode;

/**
 * 商品
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{

    use StockWarning;

    /**
     * Goods模型对象
     * @var \app\admin\model\shopro\goods\Goods
     */
    protected $model = null;
    protected $noNeedLogin = ['qrCode'];
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\goods\Goods;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("dispatchTypeList", $this->model->getDispatchTypeList());
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
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            // list($where, $sort, $order, $offset, $limit) = $this->buildparams('title');
            $sort = $this->request->get("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
            $sort = $sort == 'price' ? 'convert(`price`, DECIMAL(10, 2))' : $sort;
            $order = $this->request->get("order", "DESC");


            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $activity_type = $this->request->get("activity_type", 'all');   // 活动类型

            $total = $this->buildSearchOrder()->count();

            // 构建查询数据条件
            $list = $this->buildSearchOrder();
            $recommended = $this->request->get("recommended", 'all');



            $subsql = \app\admin\model\shopro\goods\SkuPrice::where('status', 'up')->field('sum(stock) as stock, goods_id as sku_goods_id')->group('goods_id')->buildSql();
            $goodsTableName = $this->model->getQuery()->getTable();

            // 关联规格表，获取总库存
            $list = $list->join([$subsql => 'w'], $goodsTableName . '.id = w.sku_goods_id', 'left');

            // 关联查询当前商品的活动，一个商品可能存在多条活动记录，使用 group_concat 搜集所有活动类型，关联条件 只有 find_in_set 会存在一个商品出现多次，所以使用 group
            $actSubSql = \app\admin\model\shopro\activity\Activity::where('starttime', '<=', time())->where('endtime', '>=', time())->buildSql();
            $list = $list->join([$actSubSql => 'act'], "(find_in_set(" . $goodsTableName . ".id, act.goods_ids) or act.goods_ids = '')", 'left');

            // 关联查询当前商品是否设置有积分
            $scoreSubSql = \app\admin\model\shopro\app\ScoreSkuPrice::field("'score' as app_type, goods_id as score_goods_id")->group('score_goods_id')->buildSql();
            $list = $list->join([$scoreSubSql => 'score'], $goodsTableName . '.id = score.score_goods_id', 'left');

            // 关闭 sql mode 的 ONLY_FULL_GROUP_BY
            $oldModes = closeStrict(['ONLY_FULL_GROUP_BY']);


            $list = $list->field("$goodsTableName.*, w.*,score.*,group_concat(act.type) as activity_type, act.goods_ids")
                ->group('id')
                ->orderRaw($sort . ' ' . $order)
                ->limit($offset, $limit)
                ->select();

            // 恢复 sql mode
            recoverStrict($oldModes);

            foreach ($list as $row) {
                $row->visible(['id','video','videoqrcode','type', 'activity_id', 'activity_type', 'is_sku', 'app_type', 'title', 'status', 'weigh', 'category_ids', 'image', 'price', 'likes', 'views', 'sales', 'stock', 'show_sales', 'dispatch_type', 'updatetime','recommended']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            if ($this->request->get("page_type") == 'select') {
                return json($result);
            }

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $sku = $this->request->post("sku/a");

            if ($params) {
                $params = $this->preExcludeFields($params);

                if (!$params['is_sku']) {
                    // 单规格，price 必须是数字
                    if (!preg_match('/^[0-9]+(.[0-9]{1,8})?$/', $params['price'])) {
                        $this->error("请填写正确的价格");
                    }
                }
                if(isset($params['video']) && $params['video']){
                    $qrCode    = $this->buildQrcode($params['video']);
                    $params['videoqrcode'] =  $this->serverUrl().'/uploads/qrcode/'.$qrCode;
                }
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->validateFailException(true)->validate('\app\admin\validate\shopro\Goods.add')->allowField(true)->save($params);
                    if ($result) {
                        $this->editSku($this->model, $sku, 'add');
                        Db::commit();
                    }

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
                    $this->success("添加成功");
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }



    /**
     * 查看详情
     */
    public function detail($ids = null) {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->append(['category_ids_arr', 'params_arr', 'dispatch_group_ids_arr']);

        $result = [];

        if ($row['is_sku']) {
            $skuList = \app\admin\model\shopro\goods\Sku::all(['pid' => 0, 'goods_id' => $ids]);
            if ($skuList) {
                foreach ($skuList as &$s) {
                    $s->children = \app\admin\model\shopro\goods\Sku::all(['pid' => $s->id, 'goods_id' => $ids]);
                }
            }
            $result['skuList'] = $skuList;

            $skuPrice = \app\admin\model\shopro\goods\SkuPrice::all(['goods_id' => $ids]);
            $result['skuPrice'] = $skuPrice;
        } else {
            // 将单规格的部分数据直接放到 row 上
            $goodsSkuPrice = \app\admin\model\shopro\goods\SkuPrice::where('goods_id', $ids)->order('id', 'asc')->find();

            $row['stock'] = $goodsSkuPrice['stock'] ?? 0;
            $row['sn'] = $goodsSkuPrice['sn'] ?? "";
            $row['weight'] = $goodsSkuPrice['weight'] ?? 0;
            $row['stock_warning'] = $goodsSkuPrice['stock_warning'];

            $result['skuList'] = [];
            $result['skuPrice'] = [];
        }
        $result['detail'] = $row;

        return $this->success('获取成功', null, $result);
    }



    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if(!$ids) {
            $ids = $this->request->get('id');
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->updatetime = time();

        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $sku = $this->request->post("sku/a");
            if(isset($params['video']) && $params['video'] && $params['video'] != $row['video']){
                $qrCode    = $this->buildQrcode($params['video']);
                $params['videoqrcode'] =  $this->serverUrl().'/uploads/qrcode/'.$qrCode;
            }
            if ($params) {
                $this->excludeFields = ['is_sku', 'type'];
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    $result = $row->validateFailException(true)->validate('\app\admin\validate\shopro\Goods.edit')->allowField(true)->save($params);
                    if ($result) {
                        $this->editSku($row, $sku, 'edit');
                        Db::commit();
                    }
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
                    $this->success("编辑成功");
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        $skuList = \app\admin\model\shopro\goods\Sku::all(['pid' => 0, 'goods_id' => $ids]);
        if ($skuList) {
            foreach ($skuList as &$s) {
                $s->children = \app\admin\model\shopro\goods\Sku::all(['pid' => $s->id, 'goods_id' => $ids]);
            }
        }
        $this->assignconfig('skuList', $skuList);
        $skuPrice = \app\admin\model\shopro\goods\SkuPrice::all(['goods_id' => $ids]);
        $this->assignconfig('skuPrice', $skuPrice);
        return $this->view->fetch();
    }

    public function select()
    {
        if ($this->request->isAjax()) {
            return $this->index();
        }
        $categoryModel = new \app\admin\model\shopro\Category;
        $category = $categoryModel->with('children.children.children')->where('pid', 0)->order('weigh desc, id asc')->select();
        $this->assignconfig('category', $category);
        return $this->view->fetch();
    }


    public function setStatus($ids, $status) {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            // dump($status);dump($recommended);die;
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    if($status == 'yes' || $status == 'no'){
                        $v->recommended = $status;
                    }else{
                       $v->status = $status;
                    }
                    $count += $v->save();
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
                $this->error(__('No rows were updated'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    protected function editSku($goods, $sku, $type = 'add')
    {
        if ($goods['is_sku']) {
            // 多规格
            $this->editMultSku($goods, $sku, $type);
        } else {
            $this->editSimSku($goods, $sku, $type);
        }

    }


    /**
     * 添加编辑单规格
     */
    protected function editSimSku($goods, $sku, $type = 'add') {
        $params = $this->request->post("row/a");

        $data = [
            "goods_id" => $goods['id'],
            "stock" => $params['stock'] ?? 0,
            "stock_warning" => isset($params['stock_warning']) && is_numeric($params['stock_warning'])
                                     ? $params['stock_warning'] : null,
            "sn" => $params['sn'] ?? "",
            "weight" => $params['weight'] ? intval($params['weight']) : 0,
            "price" => $params['price'] ?? 0,
            "status" => 'up'
        ];

        if ($type == 'add') {
            $goodsSkuPrice = new \app\admin\model\shopro\goods\SkuPrice();
        } else {
            // 查询
            $goodsSkuPrice = \app\admin\model\shopro\goods\SkuPrice::where('goods_id', $goods['id'])->order('id', 'asc')->find();
            if (!$goodsSkuPrice) {
                $goodsSkuPrice = new \app\admin\model\shopro\goods\SkuPrice();
            }
        }

        $goodsSkuPrice->save($data);

        // 检测库存预警
        $this->checkStockWarning($goodsSkuPrice);
    }


    /**
     * 添加编辑多规格
     */
    protected function editMultSku($goods, $sku, $type = 'add') {
        $skuList = json_decode($sku['listData'], true);
        $skuPrice = json_decode($sku['priceData'], true);
        if (count($skuList) < 1) {
            throw Exception('请填写规格列表');
        }
        foreach ($skuList as $key => $sku) {
            if (count($sku['children']) <= 0) {
                throw Exception('主规格至少要有一个子规格');
            }

            // 验证子规格不能为空
            foreach ($sku['children'] as $k => $child) {
                if (!isset($child['name']) || empty(trim($child['name']))) {
                    throw Exception('子规格不能为空');
                }
            }
        }

        if (count($skuPrice) < 1) {
            throw Exception('请填写规格价格');
        }


        foreach ($skuPrice as &$price) {
            if (empty($price['price']) || $price['price'] == 0) {
                throw Exception('请填写规格价格');
            }
            if ($price['stock'] === '') {
                throw Exception('请填写规格库存');
            }
            if (empty($price['weight'])) {
                $price['weight'] = 0;
            }
        }

        // 编辑保存规格项
        $allChildrenSku = $this->saveSkuList($goods, $skuList, $type);

        if ($type == 'add') {
            // 创建新产品，添加规格列表和规格价格
            foreach ($skuPrice as $s3 => &$k3) {
                $k3['goods_sku_ids'] = $this->checkRealIds($k3['goods_sku_temp_ids'], $allChildrenSku);
                $k3['goods_id'] = $goods->id;
                $k3['goods_sku_text'] = implode(',', $k3['goods_sku_text']);
                $k3['weight'] = intval($k3['weight']);
                $k3['createtime'] = time();
                $k3['updatetime'] = time();

                unset($k3['id']);
                unset($k3['temp_id']);      // 前端临时 id
                unset($k3['goods_sku_temp_ids']);       // 前端临时规格 id,查找真实 id 用
            }
            $res = (new \app\admin\model\shopro\goods\SkuPrice)->allowField(true)->saveAll($skuPrice);

            // 检测库存预警
            $this->checkAllStockWarning($res, 'add');
        } else {
            // 编辑旧商品，先删除老的不用的 skuPrice
            $oldSkuPriceIds = array_column($skuPrice, 'id');
            // 删除当前商品老的除了在基础上修改的skuPrice
            \app\admin\model\shopro\goods\SkuPrice::where('goods_id', $goods->id)
                            ->where('id', 'not in', $oldSkuPriceIds)->delete();

            // 删除失效的库存预警记录
            $this->delNotStockWarning($oldSkuPriceIds, $goods->id);

            foreach ($skuPrice as $s3 => &$k3) {
                $data['goods_sku_ids'] = $this->checkRealIds($k3['goods_sku_temp_ids'], $allChildrenSku);
                $data['goods_id'] = $goods->id;
                $data['goods_sku_text'] = implode(',', $k3['goods_sku_text']);
                $data['weigh'] = $k3['weigh'];
                $data['image'] = $k3['image'];
                $data['stock'] = $k3['stock'];
                $data['stock_warning'] = $k3['stock_warning'];
                $data['sn'] = $k3['sn'];
                $data['weight'] = intval($k3['weight']);
                $data['price'] = $k3['price'];
                $data['status'] = $k3['status'];
                $data['createtime'] = time();
                $data['updatetime'] = time();

                if ($k3['id']) {
                    // 编辑
                    $goodsSkuPrice = \app\admin\model\shopro\goods\SkuPrice::get($k3['id']);
                } else {
                    // 新增数据
                    $goodsSkuPrice = new \app\admin\model\shopro\goods\SkuPrice();
                }

                if ($goodsSkuPrice) {
                    $goodsSkuPrice->save($data);

                    // 检测库存预警
                    $this->checkStockWarning($goodsSkuPrice);
                }
            }
        }
    }


    // 根据前端临时 temp_id 获取真实的数据库 id
    private function checkRealIds($newGoodsSkuIds, $allChildrenSku)
    {
        $newIdsArray = [];
        foreach ($newGoodsSkuIds as $id) {
            $newIdsArray[] = $allChildrenSku[$id];
        }
        return implode(',', $newIdsArray);

    }


    // 差异更新 规格规格项（多的删除，少的添加）
    private function saveSkuList($goods, $skuList, $type = 'add') {
        $allChildrenSku = [];

        if ($type == 'edit') {
            // 删除无用老规格
            // 拿出需要更新的老规格
            $oldSkuIds = [];
            foreach ($skuList as $key => $sku) {
                $oldSkuIds[] = $sku['id'];

                $childSkuIds = [];
                if ($sku['children']) {
                    // 子项 id
                    $childSkuIds = array_column($sku['children'], 'id');
                }

                $oldSkuIds = array_merge($oldSkuIds, $childSkuIds);
                $oldSkuIds = array_unique($oldSkuIds);
            }

            // 删除老的除了在基础上修改的规格项
            \app\admin\model\shopro\goods\Sku::where('goods_id', $goods->id)->where('id', 'not in', $oldSkuIds)->delete();
        }

        foreach ($skuList as $s1 => &$k1) {
            //添加主规格
            if ($k1['id']) {
                // 编辑
                \app\admin\model\shopro\goods\Sku::where('id', $k1['id'])->update([
                    'name' => $k1['name'],
                ]);

                $skuId[$s1] = $k1['id'];
            } else {
                // 新增
                $skuId[$s1] = \app\admin\model\shopro\goods\Sku::insertGetId([
                    'name' => $k1['name'],
                    'pid' => 0,
                    'goods_id' => $goods->id
                ]);
            }
            $k1['id'] = $skuId[$s1];
            foreach ($k1['children'] as $s2 => &$k2) {
                if ($k2['id']) {
                    // 编辑
                    \app\admin\model\shopro\goods\Sku::where('id', $k2['id'])->update([
                        'name' => $k2['name'],
                    ]);

                    $skuChildrenId[$s1][$s2] = $k2['id'];
                } else {
                    $skuChildrenId[$s1][$s2] = \app\admin\model\shopro\goods\Sku::insertGetId([
                        'name' => $k2['name'],
                        'pid' => $k1['id'],
                        'goods_id' => $goods->id
                    ]);
                }

                $allChildrenSku[$k2['temp_id']] = $skuChildrenId[$s1][$s2];
                $k2['id'] = $skuChildrenId[$s1][$s2];
                $k2['pid'] = $k1['id'];
            }
        }

        return $allChildrenSku;
    }



    // 构建查询条件
    private function buildSearchOrder()
    {
        $search = $this->request->get("search", '');        // 关键字
        $status = $this->request->get("status", 'all');
        $recommended = $this->request->get("recommended", 'all');
        $activity_type = $this->request->get("activity_type", 'all');
        $app_type = $this->request->get("app_type", 'all');
        $min_price = $this->request->get("min_price", "");
        $max_price = $this->request->get("max_price", "");
        $category_id = $this->request->get('category_id', 0);

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $goods = $this->model;

        if ($search) {
            // 模糊搜索字段
            $searcharr = ['title', 'id'];
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $tableName . $v : $v;
            }
            unset($v);
            $goods = $goods->where(function ($query) use ($searcharr, $search, $tableName) {
                $query->where(implode("|", $searcharr), "LIKE", "%{$search}%");
            });
        }

        $goods_ids = [];
        // 活动
        if ($activity_type != 'all') {
            // 同一请求，会组装两次请求条件,缓存 10 秒
            $activities = Activity::cache(10)->where('type', $activity_type)->column('goods_ids');
            foreach ($activities as $key => $goods_id) {
                $ids = explode(',', $goods_id);
                $goods_ids = array_merge($goods_ids, $ids);
            }
        }

        // 积分
        if ($app_type == 'score') {
            $score_goods_ids = \app\admin\model\shopro\app\ScoreSkuPrice::cache(10)->group('goods_id')->column('goods_id');
            $goods_ids = array_merge($goods_ids, $score_goods_ids);
        }

        $goods_ids = array_filter(array_unique($goods_ids));
        if ($goods_ids) {
            $goods = $goods->where($tableName . 'id', 'in', $goods_ids);
        } else {
            if ($activity_type != 'all' || $app_type != 'all') {
                // 搜了活动，但是 goods_ids 为空，这时候搜索结果应该为空
                $goods = $goods->where($tableName . 'id', 'in', $goods_ids);
            }
        }

        // 价格
        if ($min_price != '') {
            $goods = $goods->where('convert(`price`, DECIMAL(10, 2)) >= ' . round($min_price, 2));
        }
        if ($max_price != '') {
            $goods = $goods->where('convert(`price`, DECIMAL(10, 2)) <= ' . round($max_price, 2));
        }

        // 商品状态
        if ($status != 'all') {
            $goods = $goods->where('status', 'in', $status);
        }

        if ($recommended != 'all') {
            $goods = $goods->where('recommended', '=', $recommended);
        }
        if(isset($category_id) && $category_id != 0) {
            $category_ids = [];
                // 查询分类所有子分类,包括自己
                $category_ids = \addons\shopro\model\Category::getCategoryIds($category_id);


            $goods = $goods->where(function ($query) use ($category_ids) {
                // 所有子分类使用 find_in_set or 匹配，亲测速度并不慢
                foreach($category_ids as $key => $category_id) {
                    $query->whereOrRaw("find_in_set($category_id, category_ids)");
                }
            });
        }

        return $goods;
    }

    /**
     * 删除
     *
     * @param $ids
     * @return void
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function del($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post("ids");
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $goods_id = Env::get('vip.goods_id');
        if(in_array($goods_id, explode(',', $ids))){
            $this->error('不能删除系统指定ID为'.$goods_id.'的商品');
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $list = $this->model->where($pk, 'in', $ids)->select();
        $count = 0;
        Db::startTrans();
        try {
            foreach ($list as $item) {
                $count += $item->delete();
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }
    // 获取二维码链接
    public function serverUrl(){
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        return  $http_type . $_SERVER['HTTP_HOST'];
    }
    // 生成二维码
    public function buildQrcode($videoUrl)
    {
        $config = get_addon_config('qrcode');
        $config_alioss = get_addon_config('alioss');

        $params = $this->request->get();
        $params = array_intersect_key($params, array_flip(['text', 'size', 'padding', 'errorlevel', 'foreground', 'background', 'logo', 'logosize', 'logopath', 'label', 'labelfontsize', 'labelalignment']));

        $params['text'] = $config_alioss['cdnurl'] .'/'. $videoUrl;
        $params['label'] = $this->request->get('label', $config['label'], 'trim');

        $qrCode = \addons\qrcode\library\Service::qrcode($params);

        $mimetype = $config['format'] == 'png' ? 'image/png' : 'image/svg+xml';

        $response = Response::create()->header("Content-Type", $mimetype);

        // 直接显示二维码
        // header('Content-Type: ' . $qrCode->getContentType());
        // $response->content($qrCode->writeString());

        // 写入到文件
        $code_name = "";
        if ($config['writefile']) {
            $qrcodePath = ROOT_PATH . 'public/uploads/qrcode/';
            if (!is_dir($qrcodePath)) {
                @mkdir($qrcodePath);
            }
            if (is_really_writable($qrcodePath)) {
                $filePath = $qrcodePath . md5(implode('', $params)) . '.' . $config['format'];
                $qrCode->writeFile($filePath);
                $code_name = md5(implode('', $params)) . '.' . $config['format'];
            }
        }

        return $code_name;
    }

    private function createQrcodeFile($goods_id){
        $wxMiniProgram = json_decode(\app\admin\model\shopro\Config::get(['name' => 'wxMiniProgram'])->value, true);
        $class = new XcxCode($wxMiniProgram['app_id'],$wxMiniProgram['secret']);
        $path="pages/index/scan";
        $img=$class->mpcode($path, $goods_id, 1280);
        Log::error($img);
        $filename = ROOT_PATH.'public/uploads/goods_qrcode/'.$goods_id.'_video.png';
        $dir = ROOT_PATH.'public/uploads/goods_qrcode';
        if (!is_dir($dir))
            mkdir ($dir,0755,true);
        $file = fopen($filename,"w");//创建件准备写入，文件名xcxcode/wxcode1.jpg为自定义
        fwrite($file,$img);//写入
        fclose($file);//关闭
    }

    // 视频二维码
    public function downloadVideoQrcode(){
        $goods_id = $this->request->param('id');
        $filename = ROOT_PATH.'public/uploads/goods_qrcode/'.$goods_id.'_video.png';
        if(!file_exists($filename)){
            $this->createQrcodeFile($goods_id);
            if(!file_exists($filename)){
                halt('error');
            }
        }
        ob_start();
        readfile($filename);
        $content = ob_get_clean();
        return response($content, 200, ['Content-Length'=>strlen($content)])->contentType('image/png');
    }
}
