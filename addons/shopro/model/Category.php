<?php

namespace addons\shopro\model;

use think\Model;

/**
 * 配置模型
 */
class Category extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_category';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $hidden = ['createtime', 'updatetime', 'status'];

    // 追加属性
    protected $append = [

    ];

    public static function getCategoryDetail($id) {
        if ($id) {
            $category = self::where(['id' => $id, 'pid' => 0, 'status' => 'normal'])->find();
        } else {
            // 没有 id
            $category = self::where(['pid' => 0, 'status' => 'normal'])->order('weigh', 'desc')->order('id', 'desc')->find();
        }

        return $category;
    }


    public static function getCategoryList($id)
    {
        $category = self::where(['id' => $id, 'pid' => 0, 'status' => 'normal'])->with('children.children.children')->find();

        return $category;
    }

    private static function getAllChirdrenCategory($pid, $type = 'shop', $status = 'normal')
    {
        $where = [
            'type' => $type,
            'status' => $status,
            'pid' => $pid
        ];
        $categoryList = self::all($where);
        if ($categoryList) {
            foreach ($categoryList as $k => &$v) {
                $v['chirdren'] = self::getAllChirdrenCategory($v->id);
            }
            return $categoryList;
        }
        return [];
    }


    /**
     * 缓存递归获取子分类 id
     */
    public static function getCategoryIds($id)
    {
        // 判断缓存
        $cacheKey = 'category-' . $id . '-child-ids';
        $categoryIds = cache($cacheKey);

        if (!$categoryIds) {
            $categoryIds = self::recursionGetCategoryIds($id);
            
            // 缓存暂时注释，如果需要，可以打开，请注意后台更新分类记得清除缓存
            // cache($cacheKey, $categoryIds, (600 + mt_rand(0, 300)));     // 加入随机秒数，防止一起全部过期
        }

        return $categoryIds;
    }


    /**
     * 递归获取子分类 id
     */
    public static function recursionGetCategoryIds($id) {
        $ids = [];
        $category_ids = self::where(['pid' => $id])->column('id');
        if ($category_ids) {
            foreach ($category_ids as $k => $v) {
                $childrenCategoryIds = self::recursionGetCategoryIds($v);
                if ($childrenCategoryIds && count($childrenCategoryIds) > 0) $ids = array_merge($ids, $childrenCategoryIds);
            }
        }

        return array_merge($ids, [intval($id)]);
    }


    public function getImageAttr($value, $data)
    {
        if (!empty($value)) return cdnurl($value, true);

    }

    public function children () 
    {
        return $this->hasMany(\addons\shopro\model\Category::class, 'pid', 'id')->where('status', 'normal')->order('weigh desc, id asc');
    }

}
