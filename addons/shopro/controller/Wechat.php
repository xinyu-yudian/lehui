<?php

namespace addons\shopro\controller;

use addons\shopro\library\Wechat as WechatLibrary;
use addons\shopro\model\Wechat as WechatModel;
use addons\shopro\model\Config;

/**
 * 微信接口
 */
class Wechat extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $app = null;
    protected $userOpenId = '';
    /**
     * 微信公众号服务端API对接、处理消息回复
     */
    public function index()
    {
        $wechat = new WechatLibrary('wxOfficialAccount');
        $this->app = $wechat->getApp();
        $this->app->server->push(function ($message) {
            //初始化信息
            $this->userOpenId = $message['FromUserName'];
            // return json_encode($message, JSON_UNESCAPED_UNICODE); //调试使用

            switch ($message['MsgType']) {
                case 'event': //收到事件消息
                    switch ($message['Event']) {
                        case 'subscribe': //订阅（关注）事件
                            //获取粉丝信息并保存
                            $subscribe = WechatModel::get(['type' => 'subscribe']);
                            if ($subscribe) {
                                return $this->response($subscribe);
                            }
                            break;
                        case 'unsubscribe': //取消订阅（关注）事件
                            //获取粉丝信息并保存
                            break;
                        case 'CLICK':  //自定义菜单事件
                            return $this->response($message, 'CLICK');
                            break;
                        case 'SCAN': //扫码事件
                            return '';
                            break;
                    }
                    break;
                case 'text': //收到文本消息
                    //检测关键字回复
                    $content = $message['Content'];
                    $auto_reply = WechatModel::where('type', 'auto_reply')->where('find_in_set(:keywords,rules)', ['keywords' => $content])->find();
                    if ($auto_reply) {
                        return $this->response($auto_reply);
                    }
                case 'image': //收到图片消息
                case 'voice': //收到语音消息
                case 'video': //收到视频消息
                case 'location': //收到坐标消息
                case 'link': //收到链接消息
                case 'file': //收到文件消息
                default: // ... 默认回复消息
                    $default_reply = WechatModel::where('type', 'default_reply')->find();
                    if ($default_reply) {
                        return $this->response($default_reply);
                    }
            }
        });
        $response = $this->app->server->serve();
        // 将响应输出
        $response->send();
    }

    public function jssdk()
    {
        $params = $this->request->post();
        $apis = [
            'checkJsApi',
            'updateTimelineShareData',
            'updateAppMessageShareData',
            "onMenuShareAppMessage",
            "onMenuShareTimeline",
            'getLocation', //获取位置
            'openLocation', //打开位置
            'scanQRCode', //扫一扫接口
            'chooseWXPay', //微信支付
            'chooseImage', //拍照或从手机相册中选图接口
            'previewImage', //预览图片接口       'uploadImage', //上传图片
            'openAddress',   // 获取微信地址
        ];
        // $openTagList = [
        //     'wx-open-subscribe'
        // ];

        $uri = urldecode($params['uri']);
        
        $wechat = new WechatLibrary('wxOfficialAccount');

        $jssdk = $wechat->getApp()->jssdk->setUrl($uri);
        // easywechat 版本 < 4.2.33 的 buildConfig 方法 没有 openTagList 参数，手动覆盖底层 buildConfig 方法
        $res = $wechat->buildConfig($jssdk, $apis, $debug = false, $beta = false, $json = false);

        $this->success('sdk', $res);
    }



    /**
     * 微信公众号服务端API对接
     */
    public function wxacode()
    {
        $scene = $this->request->get('scene', '');
        $path = $this->request->get('path', '');

        if (empty($path)) {
            $path = 'pages/index/index';
        }

        $wechat = new WechatLibrary('wxMiniProgram');
        $content = $wechat->getApp()->app_code->getUnlimit($scene, [
            'page' => $path,
            'is_hyaline' => true,
        ]);

        if ($content instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            return response($content->getBody(), 200, ['Content-Length' => strlen($content)])->contentType('image/png');
        } else {
            // 小程序码获取失败
            $msg = isset($content['errcode']) ? $content['errcode'] : '-';
            $msg .= isset($content['errmsg']) ? $content['errmsg'] : '';
            \think\Log::write('wxacode-error' . $msg);

            $this->error('获取失败', $msg);
        }
    }

    /**
     * 回复消息
     */
    private function response($replyInfo, $event = 'text')
    {
        switch ($event) {
            case 'SCAN': //解析扫码事件EventKey
                break;
            case 'CLICK': //解析菜单点击事件EventKey
                $key = explode('|', $replyInfo['EventKey']);
                if ($key) {
                    $message['type'] = $key[0];
                    if ($key[0] === 'text') {
                        $message['content'] =  json_decode(WechatModel::get($key[1])->content, true);
                    } elseif($key[0] === 'link') {
                        $link = WechatModel::get($key[1]);
                        $message = array_merge($message, json_decode($link->content, true));
                        $message['title'] = $link->name;
                        // return json_encode($message);
                    }else {
                        $message['media_id'] = $key[1];
                    }
                }
                break;
            default:
                $message = json_decode($replyInfo['content'], true);
                break;
        }

        switch ($message['type']) {
            case 'text':  //回复文本
                $content = new \EasyWeChat\Kernel\Messages\Text($message['content']);
                break;
            case 'image': //回复图片
                $content = new \EasyWeChat\Kernel\Messages\Image($message['media_id']);
                break;
            case 'news': //回复图文
                $message = new \EasyWeChat\Kernel\Messages\Media($message['media_id'], 'mpnews');
                $this->app->customer_service->message($message)->to($this->userOpenId)->send();  //素材消息使用客服接口回复
                break;
            case 'voice': //回复语音
                $content = new \EasyWeChat\Kernel\Messages\Voice($message['media_id']);
                break;
            case 'video': //回复视频
                $content = new \EasyWeChat\Kernel\Messages\Video($message['media_id']);
                break;
            case 'link': //回复链接
                $items = new  \EasyWeChat\Kernel\Messages\NewsItem([
                    'title'       => $message['title'],
                    'description' => $message['description'],
                    'url'         => $message['url'],
                    'image'       => cdnurl($message['image'], true),
                    // ...
                ]);
                $content = new \EasyWeChat\Kernel\Messages\News([$items]);
                break;
        }
        return $content;
    }
}
