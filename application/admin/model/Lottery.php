<?php

namespace app\admin\model;

use app\admin\model\join\lottery\Userlist;
use think\Cache;
use think\Model;
use think\Db;
use traits\model\SoftDelete;

class Lottery extends Model
{

    use SoftDelete;



    // 表名
    protected $name = 'lottery';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'endtime_text',
        'lotterytime_text',
        'typedata_text'
    ];



    public function getTypedataList()
    {
        return [
            // '1' => __('Typedata 1'),
            '2' => __('Typedata 2'), '3' => __('Typedata 3')
        ];
    }


    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getLotterytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['lotterytime']) ? $data['lotterytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getTypedataTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['typedata']) ? $data['typedata'] : '');
        $list = $this->getTypedataList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setEndtimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setLotterytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    // 把未开奖的定时活动放到redis中
    public function initRedis()
    {
        $lists = self::where('ifstart', 0)->where('typedata', '>=', 2)->where('lotterytime', '>', time())->field('id,lotterytime,typedata,user_count')->select();
        $temp = [];
        foreach ($lists as $k => $v) {
            $one = [];
            $one['id'] = $v['id'];
            $one['lotterytime'] = $v['lotterytime'];
            $one['typedata']=$v['typedata'];
            $one['user_count']=$v['user_count'];
            $temp[] = $one;

            //统计该活动已报名人数，插入redis
            $user_count = Db::table('fa_join_lottery_userlist')->where(['lottery_id' => $v['id']])->count();
            Cache::store('redis')->set('lotterys' . $v['id'], $user_count);
        }
        Cache::store('redis')->set('lotterys', $temp);
    }

    // 开奖
    public function startLottery($lottery_id)
    {
        $info = self::where('id', $lottery_id)->where('ifstart', 0)->where('typedata', '>=', 2)->find();
        if (empty($info)) {
            return;
        }
        // 获取报名抽奖的人员
        $users = Userlist::where('lottery_id', '=', $lottery_id)->select();
        $firstUsers = [];
        $lastUsers = [];
        foreach ($users as $k => $v) {
            // 获取邀请记录，增加中奖概率
            $map = [];
            $map['user_id'] = $v['user_id'];
            $map['lottery_id'] = $v['lottery_id'];
            $invites = \app\admin\model\invite\User::where($map)->select();
            // 30天内是否中过奖
            $history = Userlist::where([
                'user_id' => $v['user_id'],
                'is_award' => 1,
                'awardtime' => ['>', strtotime(' -30 day')],
            ])->find();
            if ($history) {
                // 30天内中过奖
                $lastUsers[] = $v['user_id'];
                foreach ($invites as $kk => $vv) {
                    $lastUsers[] = $v['user_id'];
                }
            } else {
                // 30天内没中过奖
                $firstUsers[] = $v['user_id'];
                foreach ($invites as $kk => $vv) {
                    $firstUsers[] = $v['user_id'];
                }
            }
        }
        // 乱序
        shuffle($firstUsers);
        shuffle($lastUsers);
        // 获取奖品
        $awards = Award::where('lotterylist', '=', $lottery_id)->select();
        $awardsData = []; // 中奖记录
        $awardsDataTemp = []; // 中奖用户ID
        // 派奖
        foreach ($awards as $k => $v) {
            for ($i = 0; $i < $v['awartnum']; $i++) {
                if (count($firstUsers) > 0) {
                    if (!in_array($firstUsers[0], $awardsDataTemp)) {
                        // 中奖
                        $awardsDataTemp[] = $firstUsers[0];
                        $one = [];
                        $one['user_id'] = $firstUsers[0];
                        $one['award_id'] = $v['id'];
                        $one['lottery_id'] = $lottery_id;
                        $awardsData[] = $one;
                    }
                    unset($firstUsers[0]);
                    shuffle($firstUsers);
                } elseif (count($lastUsers) > 0) {
                    if (!in_array($lastUsers[0], $awardsDataTemp)) {
                        // 中奖
                        $awardsDataTemp[] = $lastUsers[0];
                        $one = [];
                        $one['user_id'] = $lastUsers[0];
                        $one['award_id'] = $v['id'];
                        $one['lottery_id'] = $lottery_id;
                        $awardsData[] = $one;
                    }
                    unset($lastUsers[0]);
                    shuffle($lastUsers);
                }
            }
        }
        // 存储奖品
        foreach ($awardsData as $k => $v) {
            $mymodel = new Userlist();
            $data = [];
            $data['award_id'] = $v['award_id'];
            $data['is_award'] = 1;
            $data['awardtime'] = time();
            $map = [];
            $map['lottery_id'] = $lottery_id;
            $map['user_id'] = $v['user_id'];
            $mymodel->save($data, $map);
        }
        // 设置其他用户为未中奖
        $mymodel = new Userlist();
        $data = [];
        $data['is_award'] = 0;
        $map = [];
        $map['lottery_id'] = $lottery_id;
        $map['is_award'] = 2;
        $mymodel->save($data, $map);
        // 活动的状态变为已开奖
        $lotteryModel = new Lottery();
        $lotteryModel->save(['ifstart' => 1], ['id' => $lottery_id]);

        $res = Db::table('fa_join_lottery_userlist')->alias('lu')
            ->join('fa_lottery l', 'l.id=lu.lottery_id', 'left')
            ->join('fa_award a', 'a.id=lu.award_id', 'left')
            ->join('fa_shopro_user_oauth u', 'u.user_id=lu.user_id', 'left')
            ->where(['lu.lottery_id' => $lottery_id, 'is_push' => 1])
            ->field('l.title as lottery_title,lu.is_award,a.title as award_title,u.openid')
            ->select();
        foreach ($res as $k => $v) {
            $this->sendMsg($v, $lottery_id);
        }
    }

    public function sendMsg($data, $lottery_id)
    {
        if ($data['is_award'] == 0) {
            $res = [
                'touser' => $data['openid'],
                'template_id' => 'c0_5sKztOy87-17ju_bObKl8UtK8J7WeQ4S0qD6XywA',
                'page' => 'pages/user/lottery/listdetaill?id=' . $lottery_id,
                'data' => [
                    'thing1' => ['value' => $data['lottery_title']],
                    'thing3' => ['value' => '您未中奖'],
                    'thing4' => ['value' => '您未中奖'],
                    'thing2' => ['value' => '您未中奖，再接再厉']
                ]
            ];
        } else {
            $res = [
                'touser' => $data['openid'],
                'template_id' => 'c0_5sKztOy87-17ju_bObKl8UtK8J7WeQ4S0qD6XywA',
                'page' => 'pages/user/lottery/listdetaill?id=' . $lottery_id,
                'data' => [
                    'thing1' => ['value' => $data['lottery_title']],
                    'thing3' => ['value' => '恭喜您中奖'],
                    'thing4' => ['value' => $data['award_title']],
                    'thing2' => ['value' => '活动已开奖，快来看看谁是幸运儿']
                ]
            ];
        }
        $access_token = (new \addons\shopro\library\Wechat('wxMiniProgram'))->getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=".$access_token['access_token'];
        $this->httpcurls($url, json_encode($res), '', 1);
    }



    public function httpcurls($url, $data, $cookie = null, $is_post = 0, $header = null, $ip = null, $port = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //初始化curl会话
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //自动设置访问header信息
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //将获取的信息以字符串返回
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //禁止进行证书验证 https
        if ($is_post == 1) {
            curl_setopt($ch, CURLOPT_POST, 1); //curl post 访问
        }
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data); //post传递的参数值
        }
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie); //携带cookie访问
        }
        if (!empty($ip)) {
            curl_setopt($ch, CURLOPT_PROXY, $ip); //如果有代理ip 此处为代理ip地址 没有则不填
            curl_setopt($ch, CURLOPT_PROXYPORT, $port); //代理ip端口
        }
        $output = curl_exec($ch);
        curl_close($ch); //关闭curl资源 并且释放
        return $output;
    }
}
