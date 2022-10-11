<?php

namespace addons\shopro\controller;

use addons\shopro\model\Category as CategoryModel;
use addons\shopro\model\Goods;

/**
 * 分类管理
 *
 * @icon   fa fa-list
 * @remark 用于统一管理网站的所有分类,分类可进行无限级分类,分类类型请在常规管理->系统配置->字典配置中添加
 */

class Category extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];



    public function detail () {
        $id = $this->request->get('id');

        $data = CategoryModel::getCategoryDetail($id);
        $this->success('商城分类', $data);
    }

    public function index()
    {
        $id = $this->request->get('id');
        $data = CategoryModel::getCategoryList($id);
        $this->success('商城分类', $data);

    }


    public function goods() {
        $params = $this->request->get();
        $category_id = $params['category_id'];

        $categories = CategoryModel::where('pid', $category_id)->where('status', 'normal')->select();

        // 获取这个分类下面的所有商品
        $goodsList = Goods::getGoodsList(array_merge($params, ['no_activity' => false]), false);

        foreach($categories as $key => $category) {
            $categoryIds = ',' . $category['id'] . ',';

            $currentCategoryGoods = [];
            foreach ($goodsList as $k => $goods) {
                $goodsCategoryIds = ',' . $goods['category_ids'] . ',';

                if (strpos($goodsCategoryIds, $categoryIds) !== false) {
                    $currentCategoryGoods[] = $goods;
                }
            }

            $categories[$key]['goods'] = $currentCategoryGoods;
        }

        $this->success('商城分类商品', $categories);
    }
}
