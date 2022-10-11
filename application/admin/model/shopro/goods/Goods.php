<?php

namespace app\admin\model\shopro\goods;

use think\Model;
use traits\model\SoftDelete;
use fast\Tree;
use app\admin\model\shopro\activity\Activity;

class Goods extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'shopro_goods';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'type_text',
        'status_text',
        'recommended_text',
        'dispatch_type_text',
        'activity_type_arr',
        'activity_type_text_arr',
        'app_type_text',
        'service_ids_arr',
        'dispatch_type_arr',
        'dispatch_ids_arr',
        'images_arr'
    ];
    
    
    public function getTypeList()
    {
        return ['normal' => __('Type normal'), 'virtual' => __('Type virtual')];
    }

    public function getStatusList()
    {
        return ['up' => __('Status up'), 'hidden' => __('Status hidden'), 'down' => __('Status down')];
    }
    public function getRecommendedList()
    {
        return ['no' => '未推荐', 'yes' => '已推荐'];
    }

    public function getDispatchTypeList()
    {
        return ['express' => __('Dispatch_type express'), 'selfetch' => __('Dispatch_type selfetch'), 'store' => __('Dispatch_type store'), 'autosend' => __('Dispatch_type autosend')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }
    public function getRecommendedTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['recommended']) ? $data['recommended'] : '');
        $list = $this->getRecommendedList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getDispatchTypeArrAttr($value, $data)
    {
        $value = isset($data['dispatch_type']) ? $data['dispatch_type'] : '';
        $valueArr = explode(',', $value);
        return $valueArr;
    }

    public function getDispatchTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['dispatch_type']) ? $data['dispatch_type'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getDispatchTypeList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    public function getActivityTypeArrAttr($value, $data) {
        $activity_types = $value ? $value : (isset($data['activity_type']) ? $data['activity_type'] : '');
        $activityTypes = array_values(array_filter(explode(',', $activity_types)));

        return $activityTypes;
    }

    public function getActivityTypeTextArrAttr($value, $data)
    {
        $activityTypes = $this->activity_type_arr;
        $list = \app\admin\model\shopro\activity\Activity::getTypeList();

        $activityTypeTextArr = [];
        foreach ($activityTypes as $key => $activity_type) {
            if (isset($list[$activity_type])) {

                $activityTypeTextArr[$activity_type] = $list[$activity_type];
            }
        }

        return $activityTypeTextArr;
    }


    public function getAppTypeTextAttr($value, $data)
    {
        return $value = (isset($data['app_type']) && $data['app_type'] == 'score') ? '积分' : '';
    }

    public function getCategoryIdsArrAttr($value, $data)
    {
        $arr = $data['category_ids'] ? explode(',', $data['category_ids']) : [];

        $category_ids_arr = [];
        if ($arr) {
            $tree = Tree::instance();
            $tree->init(collection(\app\admin\model\shopro\Category::order('weigh desc,id desc')->select())->toArray(), 'pid');

            foreach ($arr as $key => $id) {
                $category_ids_arr[] = $tree->getParentsIds($id, true);
            }
        }

        return $category_ids_arr;
    }

    public function getServiceIdsArrAttr($value, $data)
    {
        return (isset($data['service_ids']) && $data['service_ids']) ? array_values(array_filter(array_map("intval", explode(',', $data['service_ids'])))) : [];
    }

    public function getDispatchIdsArrAttr($value, $data)
    {
        return (isset($data['dispatch_ids']) && $data['dispatch_ids']) ? array_values(array_filter(array_map("intval", explode(',', $data['dispatch_ids'])))) : [];
    }

    /**
     * 编辑的时候手动调用
     */
    public function getDispatchGroupIdsArrAttr($value, $data)
    {
        $dispatch_ids_arr = $this->dispatch_ids_arr;
        $dispatchs = \app\admin\model\shopro\dispatch\Dispatch::where('id', 'in', $dispatch_ids_arr)->select();

        $group = [];
        foreach ($dispatchs as $key => $dispatch) {
            $group[$dispatch['type']] = $dispatch['id'];
        }

        return $group;
    }

    public function getImagesArrAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($data['images'])) {
            $imagesArray = explode(',', $data['images']);
            return $imagesArray;
        }
        return $imagesArray;
    }

    public function getParamsArrAttr($value, $data)
    {
        return (isset($data['params']) && $data['params']) ? json_decode($data['params'], true) : [];
    }

    protected function setDispatchTypeAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


    public function scoreGoodsSkuPrice()
    {
        return $this->hasMany(\app\admin\model\shopro\app\ScoreSkuPrice::class, 'goods_id', 'id')
        ->where('status', 'up')->order('id', 'asc');
    }

}
