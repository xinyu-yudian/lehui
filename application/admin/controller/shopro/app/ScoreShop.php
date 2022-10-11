<?php

namespace app\admin\controller\shopro\app;

use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use Exception;


/**
 * 积分商品
 *
 * @icon fa fa-circle-o
 */
class ScoreShop extends Backend
{

    /**
     * Goods模型对象
     * @var \app\admin\model\shopro\goods\Goods
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\goods\Goods;
        $this->scoreModel = new \app\admin\model\shopro\app\ScoreSkuPrice;
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

            $scoreGoodsIds = \app\admin\model\shopro\app\ScoreSkuPrice::group('goods_id')->field('goods_id')->column('goods_id');

            list($where, $sort, $order, $offset, $limit) = $this->buildparams(['id', 'title']);
            $total = $this->model
                ->where('id', 'in', $scoreGoodsIds)
                ->where($where)
                ->order($sort, $order)
                ->field('id,title,image')
                ->count();

            $list = $this->model
                ->with('scoreGoodsSkuPrice')
                ->where('id', 'in', $scoreGoodsIds)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $key => $g) {
                if (count($g['score_goods_sku_price'])) {
                    $g['score'] = $g['score_goods_sku_price'][0]['score'];
                    $g['price'] = $g['score_goods_sku_price'][0]['price'];

                    // 销量
                    $g['sales'] = array_sum(array_column($g['score_goods_sku_price'], 'sales'));
                    $g['stock'] = array_sum(array_column($g['score_goods_sku_price'], 'stock'));
                }
                $list[$key] = $g;
            }
            
            // $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);
            return $this->success('积分商城', null, $result);

        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        $id = $this->request->param('id');
        $scoreGoodsIds = \app\admin\model\shopro\app\ScoreSkuPrice::getCurrentGoodsIds();
        if(!$id || in_array($id, $scoreGoodsIds)) {
            return $this->error('该商品已经上架');
        }else{
            $goodsInfo = \app\admin\model\shopro\goods\Goods::where('id', $id)->field('id, title, image')->find();
        }
        $this->sku($id);
        if ($this->request->isPost()) {
            $goodsList = $this->request->param("goodsList");
            if ($goodsList) {
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->createOrUpdateSku($goodsList, $id);
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
        $this->assignconfig('goodsInfo', $goodsInfo);

        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($id = null)
    {
        $id = $this->request->param('id');
        $scoreGoodsIds = \app\admin\model\shopro\app\ScoreSkuPrice::getCurrentGoodsIds();
        if(!$id || !in_array($id, $scoreGoodsIds)) {
            return $this->error('该商品未上架');
        }else{
            $goodsInfo = \app\admin\model\shopro\goods\Goods::where('id', $id)->field('id, title, image')->find();
        }
        $this->sku($id);
        if ($this->request->isPost()) {
            $goodsList = $this->request->param("goodsList");
            if ($goodsList) {
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->createOrUpdateSku($goodsList, $id);
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
        $this->assignconfig('goodsInfo', $goodsInfo);
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
            $score = new \app\admin\model\shopro\app\ScoreSkuPrice;
            $list = $score->where('goods_id', 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
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

    public function select()
    {
        if ($this->request->isAjax()) {
            return $this->index();
        }
        return $this->view->fetch();
    }


    /**
     * 回收站
     */
    public function recyclebin()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);

        if ($this->request->isAjax()) {
            $scoreGoodsIds = \app\admin\model\shopro\app\ScoreSkuPrice::onlyTrashed()->group('goods_id')->field('goods_id')->column('goods_id');

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where('id', 'in', $scoreGoodsIds)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('id', 'in', $scoreGoodsIds)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 真实删除
     */
    public function destroy($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->scoreModel->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $this->scoreModel->where('goods_id', 'in', $ids);
        }
        $count = 0;
        Db::startTrans();
        try {
            $list = $this->scoreModel->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                $count += $v->delete(true);
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
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 还原
     */
    public function restore($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->scoreModel->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $this->scoreModel->where('goods_id', 'in', $ids);
        }

        $count = 0;
        Db::startTrans();
        try {
            $list = $this->scoreModel->onlyTrashed()->select();
            foreach ($list as $index => $item) {
                $count += $item->restore();
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
        }
        $this->error(__('No rows were updated'));
    }


    /**
     * 创建、更新现有积分产品规格
     */
    private function createOrUpdateSku($goodsList, $goods_id)
    {
        //下架全部规格
        \app\admin\model\shopro\app\ScoreSkuPrice::where(['goods_id' => $goods_id])->update(['status' => 'down']);
        if (empty($goodsList)) {
            throw Exception('请完善您的信息');
        }
        $goodsList = json_decode($goodsList, true);
        $activitySkuPrice = [];
        foreach ($goodsList as $k => $g) {
                if ($g['id'] == 0) {
                    unset($g['id']);
                }
                unset($g['sales']);  //不更新销量
                $g['goods_id'] = $goods_id;
                $activitySkuPrice[] = $g;
        }
        $score = new \app\admin\model\shopro\app\ScoreSkuPrice;
        return $score->allowField(true)->saveAll($activitySkuPrice);
    }

    private function sku($goods_id)
    {
        $skuList = \app\admin\model\shopro\goods\Sku::all(['pid' => 0, 'goods_id' => $goods_id]);
        if ($skuList) {
            foreach ($skuList as &$s) {
                $s->children = \app\admin\model\shopro\goods\Sku::all(['pid' => $s->id, 'goods_id' => $goods_id]);
            }
        }
        $skuPrice = \app\admin\model\shopro\goods\SkuPrice::all(['goods_id' => $goods_id]);
        //编辑
        foreach ($skuPrice as $k => &$p) {
            $activitySkuPrice[$k] = \app\admin\model\shopro\app\ScoreSkuPrice::get(['sku_price_id' => $p['id']]);
            if (!$activitySkuPrice[$k]) {
                $activitySkuPrice[$k]['id'] = 0;
                $activitySkuPrice[$k]['status'] = 'down';
                $activitySkuPrice[$k]['price'] = '';
                $activitySkuPrice[$k]['score'] = '';
                $activitySkuPrice[$k]['stock'] = 0;
                $activitySkuPrice[$k]['sales'] = 0;
                $activitySkuPrice[$k]['sku_price_id'] = $p['id'];

            }
        }

        $this->assignconfig('skuList', $skuList);
        $this->assignconfig('skuPrice', $skuPrice);
        $this->assignconfig('activitySkuPrice', $activitySkuPrice);
    }
}
