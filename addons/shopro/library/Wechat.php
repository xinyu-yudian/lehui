<?php

namespace addons\shopro\library;

use EasyWeChat\Factory;
use addons\shopro\model\Config;
use think\Model;
use fast\Http;

/**
 *
 */
class Wechat extends Model
{
    protected $config;
    protected $app;


    public function __construct($platform)
    {
        $this->setConfig($platform);
        switch ($platform) {
            case 'wxOfficialAccount':
                $this->app    = Factory::officialAccount($this->config);
                break;
            case 'wxMiniProgram':
                $this->app    = Factory::miniProgram($this->config);
                break;
            case 'App':
                $this->app    = Factory::openPlatform($this->config);
                break;
        }

        // 重新绑定 easywechat 缓存（如果需要负载均衡，必须要开启）
        // $this->rebindCache();
    }

    // 返回实例
    public function getApp() {
        return $this->app;
    }

    //小程序:获取openid&session_key
    public function code($code)
    {
        return $this->app->auth->session($code);
    }

    public function oauth()
    {
        $oauth = $this->app->oauth;
        return $oauth;
    }

    //解密信息
    public function decryptData($session, $iv, $encryptData)
    {
        $data = $this->app->encryptor->decryptData($session, $iv, $encryptData);

        return $data;
    }

    public function unify($orderBody)
    {
        $result = $this->app->order->unify($orderBody);
        return $result;
    }

    public function bridgeConfig($prepayId)
    {
        $jssdk = $this->app->jssdk;
        $config = $jssdk->bridgeConfig($prepayId, false);
        return $config;
    }

    public function notify()
    {
        $result = $this->app;
        return $result;
    }

    //获取accessToken
    public function getAccessToken()
    {
        $accessToken = $this->app->access_token;
        $token = $accessToken->getToken(); // token 数组  token['access_token'] 字符串
        //$token = $accessToken->getToken(true); // 强制重新从微信服务器获取 token.
        return $token;
    }


    /**
     * 重写 jssdk buildConfig 方法
     *
     * @param [type] $jssdk jssdk 实例
     * @param [type] $apis  要请求的 api 列表
     * @param boolean $debug    debug 
     * @param boolean $beta 
     * @param boolean $json 是否返回 json
     * @param array $openTagList    开放标签列表
     * @return void
     */
    public function buildConfig($jssdk, $jsApiList, $debug = false, $beta = false, $json = false, $openTagList = [], $url = '')
    {
        $url = $url ?: $jssdk->getUrl();
        $nonce = \EasyWeChat\Kernel\Support\Str::quickRandom(10);
        $timestamp = time();

        $signature = [
            'appId' => $this->config['app_id'],
            'nonceStr' => $nonce,
            'timestamp' => $timestamp,
            'url' => $url,
            'signature' => $jssdk->getTicketSignature($jssdk->getTicket()['ticket'], $nonce, $timestamp, $url),
        ];

        $config = array_merge(compact('debug', 'beta', 'jsApiList', 'openTagList'), $signature);

        return $json ? json_encode($config) : $config;
    }


    public function sendTemplateMessage($attributes)
    {
        extract($attributes);
        $this->app->template_message->send([
            'touser' => $openId,
            'template_id' => $templateId,
            'page' => $page,
            'form_id' => $formId,
            'data' => $data,
            'emphasis_keyword' => $emphasis_keyword
        ]);
    }


    /**
     * 发送公众号订阅消息
     *
     * @return void
     */
    public function bizsendSubscribeMessage($data) {
        $access_token = $this->getAccessToken();

        $bizsendUrl = "https://api.weixin.qq.com/cgi-bin/message/subscribe/bizsend?access_token={$access_token['access_token']}";

        $headers = ['Content-type: application/json'];
        $options = [
            CURLOPT_HTTPHEADER => $headers
        ];
        $result = Http::sendRequest($bizsendUrl, json_encode($data), 'POST', $options);

        if (isset($result['ret']) && $result['ret']) {
            // 请求成功
            $result = json_decode($result['msg'], true);
            
            return $result;
        }
        
        // 请求失败
        return ['errcode' => -1, 'msg' => $result];
    }

    // 同步小程序直播
    public function live(Array $params = [])
    {
        $default = [
            'start' => 0,
            'limit' => 10
        ];
        $params = array_merge($default, $params);
        $default = json_encode($params);


        $access_token = $this->app->access_token->getToken();
        $getRoomsListUrl = "https://api.weixin.qq.com/wxa/business/getliveinfo?access_token={$access_token['access_token']}";
        $headers = ['Content-type: application/json'];
        $options = [
            CURLOPT_HTTPHEADER => $headers
        ];
        $result = Http::sendRequest($getRoomsListUrl, $default, 'POST', $options);
        if (isset($result['ret']) && $result['ret']) {
            $msg = json_decode($result['msg'], true);
            $result = $msg;
        }

//        $result = $this->app->live->getRooms(...array_values($params));

        $rooms = [];
        if ($result && $result['errcode'] == 0 && $result['errmsg'] === 'ok') {
            $rooms = $result['room_info'];
        }

        return $rooms;
    }

    // 小程序直播回放
    public function liveReplay(array $params = [])
    {
        $default = [
            'room_id' => 0,
            'start' => 0,
            'limit' => 20
        ];

        $params = array_merge($default, $params);
        $default = json_encode($params);
        $access_token = $this->app->access_token->getToken();
        $getPlayBackListUrl = "https://api.weixin.qq.com/wxa/business/getliveinfo?access_token={$access_token['access_token']}";
        $headers = ['Content-type: application/json'];
        $options = [
            CURLOPT_HTTPHEADER => $headers
        ];
        $result = Http::sendRequest($getPlayBackListUrl, $default, 'POST', $options);
        if (isset($result['ret']) && $result['ret']) {
            $msg = json_decode($result['msg'], true);
            $result = $msg;
        }
//        $result = $this->app->live->getPlaybacks(...array_values($params));

        $liveReplay = [];
        if ($result && $result['errcode'] == 0 && $result['errmsg'] === 'ok') {
            $liveReplay = $result['live_replay'];
        }

        return $liveReplay;
    }

    public function menu($act = 'create', $buttons = '')
    {
        $result = $this->app->menu->$act($buttons);
        return $result;

    }

    // 公众号 获取所有粉丝
    public function asyncFans($nextOpenId = null, $currentPage = 1, $totalPage = 1)
    {
        $fans = $this->app->user->list($nextOpenId);
        $openIdsArray = $fans['data']['openid'];
        //放入最大10000条openid队列去执行
        \think\Queue::push('\addons\shopro\job\Wechat@createQueueByOpenIdsArray', $openIdsArray, 'shopro');
        //第一次计算总页数
        if ($currentPage === 1) {
            $totalPage = intval($fans['total'] % $fans['count'] === 0 ? $fans['total'] / $fans['count'] : ceil($fans['total'] / $fans['count']));
        }
        //有分页 递归下一页
        if ($currentPage < $totalPage) {
            $openIdsArray = array_merge($openIdsArray, $this->asyncFans($fans['next_openid'], $currentPage++, $totalPage));
        }
        if ($currentPage == $totalPage) {
            if ($totalPage == 1) {
                $code = 1;
                $msg = '同步成功';
            }else{
                $code = 1;
                $msg = '数据较大,请稍后再查看...';
            }
            return [
                'code' => $code,
                'msg' => $msg
            ];
        }
        return $openIdsArray;
    }

    //通过openid获取已经关注的用户信息
    public function getSubscribeUserInfoByOpenId(array $openIdsArray)
    {
        $result = $this->app->user->select($openIdsArray);
        return $result;
    }


    /**
     * 重新绑定 easywechat 缓存
     *
     * @return void
     */
    private function rebindCache() {
        $options = [
            // 'select' => 0        // 默认和活动缓存使用同一个 select 库，如需自定义，解开注释，并填写对应 select 库
        ];
        $redis = (new Redis($options))->getRedis();
        $cache = new \Symfony\Component\Cache\Adapter\RedisAdapter($redis);

        // 替换应用中的缓存
        $this->app->rebind('cache', $cache);
    }



    /**
     * 合并默认配置
     *
     * @param [type] $platform
     * @return void
     */
    private function setConfig($platform) {
        $debug = config('app_debug');

        $defaultConfig = [
            'log' => [
                'default' => $debug ? 'dev' : 'prod', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 测试环境
                    'dev' => [
                        'driver' => 'single',
                        'path' => '/tmp/easywechat.log',
                        'level' => 'debug',
                    ],
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => '/tmp/easywechat.log',
                        'level' => 'info',
                    ],
                ],
            ],
        ];

        // 获取对应平台的配置
        $this->config = Config::getEasyWechatConfig($platform);
        // 根据框架 debug 合并 log 配置
        $this->config = array_merge($this->config, $defaultConfig);
    }
}
