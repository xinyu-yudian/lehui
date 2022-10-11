<?php

namespace app\admin\controller\shopro\goods;

use app\common\controller\Backend;
use app\admin\controller\shopro\Base;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;
use addons\shopro\library\traits\StockWarning as StockWarningTrait;

/**
 * 库存预警
 *
 * @icon fa fa-circle-o
 */
class StockWarning extends Base
{
    use StockWarningTrait;
    
    /**
     * GoodsSkuPrice模型对象
     * @var \app\admin\model\shopro\goods\SkuPrice
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\shopro\goods\StockWarning;

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
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $nobuildfields = ['goods_title', 'stock_type'];
            list($where, $sort, $order, $offset, $limit) = $this->custombuildparams(null, $nobuildfields);

            $skuPriceTableName = (new \app\admin\model\shopro\goods\SkuPrice())->getQuery()->getTable();

            $total = $this->buildSearch()
                        ->alias('g')
                        ->join($skuPriceTableName . ' sp', 'g.goods_sku_price_id = sp.id', 'left')
                        ->field('g.*,sp.stock')
                        ->where($where)
                        ->count();

            $list = $this->buildSearch()
                        ->alias('g')
                        ->with(['goods' => function ($query) {
                            $query->field('id,type,title,subtitle,status,category_ids,image,images,price,original_price,is_sku,dispatch_type,service_ids,dispatch_ids');
                        }])
                        ->join($skuPriceTableName . ' sp', 'g.goods_sku_price_id = sp.id', 'left')
                        ->field('g.*,sp.stock')
                        ->where($where)
                        ->order($sort, $order)
                        ->limit($offset, $limit)
                        ->select();

            $warning_total = $this->buildSearch()->alias('g')
                                ->join($skuPriceTableName . ' sp', 'g.goods_sku_price_id = sp.id', 'left')
                                ->field('g.*,sp.stock')
                                ->where($where)
                                ->where('stock', '>', 0)
                                ->count();
            $over_total = $this->buildSearch()->alias('g')
                                ->join($skuPriceTableName . ' sp', 'g.goods_sku_price_id = sp.id', 'left')
                                ->field('g.*,sp.stock')
                                ->where($where)
                                ->where('stock', '<=', 0)
                                ->count();

            $result = [
                "total" => $total,
                'warning_total' => $warning_total,
                'over_total' => $over_total,
                "rows" => $list
            ];

            return $this->success('操作成功', null, $result);
        }
        return $this->view->fetch();
    }


    /**
     * 补货
     *
     * @param [type] $ids
     * @param [type] $stock
     * @return void
     */
    public function addStock ($ids) {
        if (!$ids) {
            $ids = $this->request->get('id');
        }
        $stock = $this->request->post('stock', 0);
        if ($stock <= 0) {
            $this->error('请输入补货数量');
        }

        $row = $this->model->with(['skuPrice'])->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        Db::startTrans();
        try {
            $skuPrice = $row['skuPrice'];
            if ($skuPrice) {
                $skuPrice->setInc('stock', $stock);
            }

            // 检测库存预警
            $this->checkStockWarning($skuPrice);

            Db::commit();
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success('补货成功');
    }


    /**
     * 回收站
     */
    public function recyclebin()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->onlyTrashed()
                ->with('goods')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }


    private function buildSearch () {
        $filter = $this->request->get("filter", '');
        $filter = (array)json_decode($filter, true);
        $filter = $filter ? $filter : [];

        $goods_title = isset($filter['goods_title']) ? $filter['goods_title'] : '';
        $stock_type = isset($filter['stock_type']) ? $filter['stock_type'] : 'all';

        $name = $this->model->getQuery()->getTable();
        $tableName = $name . '.';

        $stockWarning = $this->model;

        // 商品名称
        if ($goods_title) {
            $stockWarning = $stockWarning->whereExists(function ($query) use ($goods_title, $tableName) {
                $goodsTableName = (new \app\admin\model\shopro\goods\Goods())->getQuery()->getTable();

                $query = $query->table($goodsTableName)->where($goodsTableName . '.id=g.goods_id');

                $query = $query->where('title', 'like', "%{$goods_title}%");

                return $query;
            });
        }

        if ($stock_type != 'all') {
            if ($stock_type == 'over') {
                $stockWarning = $stockWarning->where('stock', '<=', 0);
            } else {
                $stockWarning = $stockWarning->where('stock', '>', 0);
            }
        }

        return $stockWarning;
    }
}
