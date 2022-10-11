<?php

namespace addons\shopro\model;

use think\Model;
use addons\shopro\exception\Exception;
use addons\shopro\library\traits\model\app\SyncLive;
use think\Db;
use traits\model\SoftDelete;

/**
 * 直播
 */
class Live extends Model
{
    use SoftDelete, SyncLive;

    // 表名,不含前缀
    protected $name = 'shopro_live';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    CONST STATUS_LIVING = 101;      // 直播中：表示主播正常开播，直播正常的状态
    CONST STATUS_NOTICE = 102;      // 未开始：表示主播还未开播
    CONST STATUS_LIVED = 103;       // 已结束：表示在直播端点击【结束】按钮正常关闭的直播，或直播异常 15 分钟后系统强制结束的直播
    CONST STATUS_DISABLED = 104;    // 禁播：表示因违规受到运营处罚被禁播
    CONST STATUS_PAUSE = 105;       // 暂停中：表示在 MP 小程序后台-控制台内操作暂停了直播
    CONST STATUS_CATCH = 106;       // 异常：表示主播离开、切后台、断网等情况，该直播被判定为异常状态，15 分钟内恢复即可回到正常直播中的状态；如果 15 分钟后还未恢复，直播间会被系统强制结束直播
    CONST STATUS_EXPIRED = 107;     // 已过期：表示直播间一直未开播，且已

    // 追加属性
    protected $append = [
        'live_status_name'
    ];

    // 预告，未开始
    public function scopeNotice($query)
    {
        return $query->where('live_status', self::STATUS_NOTICE);
    }

    // 直播中
    public function scopeLiving($query)
    {
        return $query->where('live_status', self::STATUS_LIVING);
    }

    // 已结束
    public function scopeLived($query)
    {
        return $query->where('live_status', self::STATUS_LIVED);
    }


    // 状态中文
    public function getLiveStatusNameAttr($value, $data) {
        $status_name = '';

        switch ($data['live_status']) {
            case self::STATUS_LIVING:
                $status_name = '直播中';
                break;
            case self::STATUS_NOTICE:
                $status_name = '未开始';
                break;
            case self::STATUS_LIVED:
                $status_name = '已结束';
                break;
            case self::STATUS_DISABLED:
                $status_name = '禁播';
                break;
            case self::STATUS_PAUSE:
                $status_name = '暂停中';
                break;
            case self::STATUS_CATCH:
                $status_name = '异常';
                break;
            case self::STATUS_EXPIRED:
                $status_name = '已过期';
                break;
        }

        return $status_name;
    }


    public function goods() {
        return $this->hasMany(\addons\shopro\model\LiveGoods::class, 'live_id', 'id');
    }

    public function links() {
        return $this->hasMany(\addons\shopro\model\LiveLink::class, 'live_id', 'id');
    }
}
