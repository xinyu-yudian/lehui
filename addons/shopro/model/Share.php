<?php

namespace addons\shopro\model;

use think\Env;
use think\Model;
use addons\shopro\exception\Exception;

/**
 * 购物车模型
 */
class Share extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_share';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $hidden = [];

    // 追加属性
    protected $append = [];

    protected static $eventMap = [
        'type' => [
            'index' => '首页',
            'goods' => '产品',
            'groupon' => '拼团'
        ],
        'share_platform' => [
            'H5' => 'H5',
            'wxOfficialAccount' => '微信公众号',
            'wxMiniProgram' => '微信小程序',
            'App' => 'APP',
        ],
        'from' => [
            'forward' =>  '分享转发',
            'poster' => '分享海报',
            'link' => '分享链接'
        ]
    ];

    public static function add($spm)
    {
        $user = User::info();
        $shareParams = [];
        $spm = explode('.', $spm);
        $shareParams['share_id'] = intval($spm[0]);
        $shareParams['user_id'] = $user->id;

        // 不能分享给自己
        if ($user->id == $shareParams['share_id']) {
            throw \Exception('不能分享给本人');
        }

        $shareUser = User::get($shareParams['share_id']);
        if (empty($shareUser)) {
            return false;
            // throw \Exception('未找到分享人');
        }

        // 判断入口
        $typeArray = array_keys(self::$eventMap['type']);
        if(isset($typeArray[$spm[1] - 1])) {
            $type = $typeArray[$spm[1] - 1];
        }else {
            return false;
            // throw \Exception('错误的分享页面');
        }
        $shareParams['type'] = $type;
        $shareParams['type_id'] = $spm[2];

        // 判断来源
        $sharePlatformArray = array_keys(self::$eventMap['share_platform']);
        if(isset($sharePlatformArray[$spm[3] - 1])) {
            $share_platform = $sharePlatformArray[$spm[3] - 1];
        }else {
            return false;
            // throw \Exception('错误的分享平台');
        }
        $shareParams['share_platform'] = $share_platform;

        $fromArray = array_keys(self::$eventMap['from']);
        if(isset($fromArray[$spm[1] - 1])) {
            $from = $fromArray[$spm[1] - 1];
        }else {
            return false;
            // throw \Exception('错误的分享来源');
        }
        $shareParams['from'] = $from;

        // 新用户不能分享给老用户 按需打开 TODO:分享配置可设置
        // if($user->id > $spm['share_id']) {
        //    throw \Exception('不是新用户');
        // }

        // 查询用户最后一条，并且时间是在一小时之内的
        $last = self::where($shareParams)->where('createtime', '>', (time() - 3600))->order('id', 'desc')->find();
        if ($last) {
            return false;
            // throw \Exception('已有相似记录');
        }

        // 记录第一次分享
        if($user->pid == 0){
            $data = [];
            $data['pid'] = $shareParams['share_id'];
            $model = new \app\admin\model\User();
            $model->save($data, ['id'=>$user->id]);

            // 分享者和被分享者都赠送30元代金券
            UserCoupons::create([
                'user_id' => $shareParams['share_id'],
                'coupons_id' => Env::get('share.coupons_id'),
            ]);
            UserCoupons::create([
                'user_id' => $user->id,
                'coupons_id' => Env::get('share.coupons_id'),
            ]);

            // 如果是活动的分享，增加中奖概率
            if($shareParams['type'] == 1){
                $data = [];
                $data['user_id'] = $shareParams['share_id'];
                $data['invite_user_id'] = $user->id;
                $data['lottery_id'] = $shareParams['type_id'];
                $inviteModel = new \app\admin\model\invite\User();
                $inviteModel->save($data);
            }
        }

        $shareParams['createtime'] = time();
        $platform = request()->header('platform');
        if(!$platform) return false;
        $shareParams['platform'] = $platform;

        $share = self::create($shareParams);
        return $share;
    }


    /**
     * 分享记录
     */
    public static function getList($params)
    {
        $user = User::info();
        extract($params);
        $type = $type ?? 'all';

        $shares = self::with(['user' => function ($query) {
            $query->withField('id,nickname,avatar');
        }])->where('share_id', $user->id);

        if ($type != 'all' && in_array($type, ['index', 'goods', 'groupon'])) {
            $shares = $shares->{$type}();
        }

        $shares = $shares->order('id', 'desc')->paginate($per_page ?? 10);
        $shares = $shares->toArray();

        // 取出来商品和拼团信息，专门进行查询
        $sharesData = $shares['data'];
        $goodsIds = [];
        $grouponIds = [];
        foreach ($sharesData as $key => $data) {
            if ($data['type'] == 'goods') {
                $goodsIds[] = $data['type_id'];
            } else if ($data['type'] == 'groupon') {
                $grouponIds[] = $data['type_id'];
            }
        }

        // 查询关联的商品
        $goodsFields = 'id,title,subtitle,image,price,dispatch_type';
        if ($goodsIds) {
            $goods = Goods::where('id', 'in', $goodsIds)->field($goodsFields)->select();
            $goods = array_column(collection($goods)->toArray(), null, 'id');
        }
        // 查询关联的拼团
        if ($grouponIds) {
            $groupons = ActivityGroupon::where('id', 'in', $grouponIds)->with(['goods' => function ($query) use ($goodsFields) {
                $query->withField($goodsFields);
            }])->select();
            $groupons = array_column(collection($groupons)->toArray(), null, 'id');
        }

        // 组合数据
        foreach ($sharesData as $key => &$share) {
            if ($share['type'] == 'goods') {
                $share['type_data'] = $goods[$share['type_id']] ?? null;
            } else if ($share['type'] == 'groupon') {
                $share['type_data'] = $groupons[$share['type_id']] ?? null;
            } else {
                $share['type_data'] = null;
            }

            // 提示信息
            $share['msg'] = '通过您的' . (self::$eventMap['from'][$share['from']]) . '访问了' . self::getLookMsg($share, $user);
        }

        $shares['data'] = $sharesData;
        return $shares;
    }

    /**
     * 拼接查看内容
     */
    private static function getLookMsg($data, $user)
    {
        if ($data['type'] == 'groupon') {
            if ($data['type_data'] && $data['type_data']['user_id'] == $user->id) {
                $msg = '您发起的拼团';
            } else {
                $msg = '您参与的拼团';
            }
        } else if ($data['type'] == 'goods') {
            $msg = $data['type_data'] ? '商品“' . $data['type_data']['title'] . "”" : '商城';
        } else {
            $msg = '商城';
        }
        return $msg;
    }


    public function scopeIndex($query)
    {
        return $query->where('type', 'index');
    }

    public function scopeGoods($query)
    {
        return $query->where('type', 'goods');
    }

    public function scopeGroupon($query)
    {
        return $query->where('type', 'groupon');
    }


    public function user()
    {
        return $this->belongsTo(\addons\shopro\model\User::class, 'user_id')->field('id,nickname,avatar');
    }

    /**
     * 查最近的分销商分享记录
     *
     * @return object
     */
    public static function checkLatestShareLogByAgent($userId)
    {
        return self::where([
            'user_id' => $userId,
        ])->whereExists(function ($query) {
            $agent_table_name = (new \addons\shopro\model\commission\Agent())->getQuery()->getTable();
            return $query->table($agent_table_name)->where('share_id=' . $agent_table_name . '.user_id')->where('status', 'in', ['normal', 'freeze']);
        })->order('id desc')->find();
    }
}
