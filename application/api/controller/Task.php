<?php

namespace app\api\controller;

use app\admin\model\join\lottery\Userlist;
use app\admin\model\Lottery;
use app\common\controller\Api;
use think\Db;
use think\Cache;

/**
 * 定时任务
 */
class Task extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 初始化
     */
    public function lotteryInit()
    {
        $lotteryModel = new Lottery();
        $lotteryModel->initRedis();
    }

    public function lotteryShow()
    {
        $lists = Cache::store('redis')->get('lotterys');
        halt($lists);
    }

    /**
     * 每分钟处理，如果有未开奖的，就开奖
     */
    public function lotteryStart()
    {
        $lotterys = Cache::store('redis')->get('lotterys');
        if ($lotterys && count($lotterys) > 0) {
            $newlists = [];
            $change = 0;
            foreach ($lotterys as $k => $v) {
                if ($v['typedata'] == 2) { //定时开奖
                    if ($v['lotterytime'] <= time()) {
                        $lotteryModel = new Lottery();
                        $lotteryModel->startLottery($v['id']);
                        Cache::store('redis')->rm('lotterys' . $v['id']); 
                        $change = 1;
                    } else {
                        $newlists[] = $v;
                    }
                } else if ($v['typedata'] == 3) {   //参与人数
                    $user_count = Cache::store('redis')->get('lotterys' . $v['id']);

                    if ($v['lotterytime'] <= time()) {
                        $lotteryModel = new Lottery();
                        $lotteryModel->startLottery($v['id']);
                        $change = 1;
                        Cache::store('redis')->rm('lotterys' . $v['id']); 

                    } else if ($user_count >= $v['user_count']) {
                        $lotteryModel = new Lottery();
                        $lotteryModel->startLottery($v['id']);
                        $change = 1;
                        Cache::store('redis')->rm('lotterys' . $v['id']); 
                    } else {
                        $newlists[] = $v;
                    }
                }
            }
            if ($change == 1) {
                Cache::store('redis')->set('lotterys', $newlists);
            }
        }
    }


    public function mystart()
    {
        die();
        $lotteryModel = new Lottery();
        $lotteryModel->startLottery(25);
        die('ok');
    }

    public function testPush()
    {
        $res = [
            'touser' => 'olHdR42l6B2TQwk8kqhC_BkgB8NE',
            'template_id' => 'c0_5sKztOy87-17ju_bObKl8UtK8J7WeQ4S0qD6XywA',
            'page' => 'pages/user/lottery/listdetaill?id=' . 2,
            'data' => [
                'thing1' => ['value' => '测试推送'],
                'thing3' => ['value' => '恭喜您中奖'],
                'thing4' => ['value' => '成功'],
                'thing2' => ['value' => '活动已开奖，快来看看谁是幸运儿']
            ]
        ];
        $access_token = (new \addons\shopro\library\Wechat('wxMiniProgram'))->getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=" . $access_token['access_token'];
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
