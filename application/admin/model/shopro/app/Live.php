<?php

namespace app\admin\model\shopro\app;

use think\Model;
use traits\model\SoftDelete;
use addons\shopro\library\traits\model\app\SyncLive;

class Live extends Model
{

    use SoftDelete, SyncLive;

    

    // 表名
    protected $name = 'shopro_live';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'live_status_text',
        'starttime_text',
        'endtime_text'
    ];


    const STATUS_LIVING = 101;      // 直播中：表示主播正常开播，直播正常的状态
    const STATUS_NOTICE = 102;      // 未开始：表示主播还未开播
    const STATUS_LIVED = 103;       // 已结束：表示在直播端点击【结束】按钮正常关闭的直播，或直播异常 15 分钟后系统强制结束的直播
    const STATUS_DISABLED = 104;    // 禁播：表示因违规受到运营处罚被禁播
    const STATUS_PAUSE = 105;       // 暂停中：表示在 MP 小程序后台-控制台内操作暂停了直播
    const STATUS_CATCH = 106;       // 异常：表示主播离开、切后台、断网等情况，该直播被判定为异常状态，15 分钟内恢复即可回到正常直播中的状态；如果 15 分钟后还未恢复，直播间会被系统强制结束直播
    const STATUS_EXPIRED = 107;     // 已过期：表示直播间一直未开播，且已

    
    public function getLiveStatusList()
    {
        return ['101' => __('Live_status 101'), '102' => __('Live_status 102'), '103' => __('Live_status 103'), '104' => __('Live_status 104'), '105' => __('Live_status 105'), '106' => __('Live_status 106'), '107' => __('Live_status 107')];
    }


    public function getLiveStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['live_status']) ? $data['live_status'] : '');
        $list = $this->getLiveStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStarttimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['starttime']) ? $data['starttime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setStarttimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setEndtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function goods()
    {
        return $this->hasMany(\addons\shopro\model\LiveGoods::class, 'live_id', 'id');
    }

    public function links()
    {
        return $this->hasMany(\addons\shopro\model\LiveLink::class, 'live_id', 'id');
    }

}
