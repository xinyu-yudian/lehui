<?php

namespace addons\shopro\controller;

use addons\shopro\model\Config;
use think\Db;
use think\Config as FaConfig;
use fast\Random;
use think\exception\HttpResponseException;
use addons\shopro\library\commission\Agent as AgentLibrary;
use addons\shopro\library\commission\Commission as CommissionLibrary;
use addons\shopro\library\commission\Reward as RewardLibrary;
use addons\shopro\model\Order;

class Index extends Base
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    public function index()
    {
    }

    // 初始化商城数据 服务器压力大可以把最后的data数据存入cache缓存中来调用，防止多次查sql
    public function init()
    {
        $platform = $this->request->header('platform'); // 获取平台标识
        if (!in_array($platform, ['H5', 'App', 'wxMiniProgram', 'wxOfficialAccount'])) {
            $this->error('请使用正确客户端访问');
        }
        $data = [];     // 设置信息
        $configFields = ['shopro', 'share', 'chat', 'store', 'withdraw', $platform];    // 定义设置字段
        $configModel = new \addons\shopro\model\Config;
        $config = $configModel->where('name', 'in', $configFields)->column('value', 'name');

        // 商城基本设置
        $shoproConfig = json_decode($config['shopro'], true);
        $shoproConfig['logo'] = cdnurl($shoproConfig['logo'], true);
        $data['shop'] = $shoproConfig;

        // 支付设置
        $payment = $configModel->where('group', 'payment')->select();
        $paymentConfig = [];
        foreach ($payment as $key => $v) {
            $val = json_decode($v->value, true);
            if ($val && in_array($platform, $val['platform'])) {
                $paymentConfig[] = $v->name;
            }
        }
        $data['payment'] = $paymentConfig;        // 平台支持的支付方式

        // 平台设置
        $platformConfig = json_decode($config[$platform], true);
        if (in_array($platform, ['wxOfficialAccount', 'wxMiniProgram'])) {
            if (isset($platformConfig['auto_login']) && $platformConfig['auto_login'] == 1) {
                $autologin = true;
            } else {
                $autologin = false;
            }
            $data['wechat'] = [
                'appid' => isset($platformConfig['app_id']) ? $platformConfig['app_id'] : '',
                'autologin' => $autologin,
            ];
        }

        // 分享设置
        $shareConfig = json_decode($config['share'], true);
        $data['share'] = [
            'title' => $shareConfig['title'],
            'image' => isset($shareConfig['image']) ? cdnurl($shareConfig['image'], true) : '',
            'goods_poster_bg' => isset($shareConfig['goods_poster_bg']) ? cdnurl($shareConfig['goods_poster_bg'], true) : '',
            'user_poster_bg' => isset($shareConfig['user_poster_bg']) ? cdnurl($shareConfig['user_poster_bg'], true) : '',
            'groupon_poster_bg' => isset($shareConfig['groupon_poster_bg']) ? cdnurl($shareConfig['groupon_poster_bg'], true) : '',
        ];

        $withdrawConfig = json_decode($config['withdraw'], true);
        $recharge = $withdrawConfig['recharge'] ?? [];
        $data['recharge'] = [
            'enable' => $recharge['enable'] ?? 0,
            'methods' => $recharge['methods'] ?? [],
            'moneys' => $recharge['moneys'] ?? [],
        ];

        // 插件设置
        $data['addons'] = array_keys(get_addon_list());

        // 客服设置
        $data['chat'] =  isset($config['chat']) ? json_decode($config['chat'], true) : [];
        // 门店配置
        $data['store'] = isset($config['store']) ? json_decode($config['store'], true) : [];
        $this->success('初始化数据', $data);
    }

    // 商城模板数据
    public function template()
    {
        $get = $this->request->get();
        $platform = $this->request->header('platform');
        if (isset($get['shop_id']) && $get['shop_id'] != 0) {
            $template = \addons\shopro\model\Decorate::getCurrentPlatformDecorate('preview', $get['shop_id']);
        } else {
            $template = \addons\shopro\model\Decorate::getCurrentPlatformDecorate($platform);
        }
        $this->success('模板数据1', $template);
    }

    // 自定义页面
    public function custom()
    {
        $get = $this->request->get();
        $decorate = \addons\shopro\model\Decorate::get($get['custom_id']);
        if (!$decorate) {
            $this->error('未找到自定义页面');
        }
        $decorate->template = \addons\shopro\model\Decorate::getCustomDecorate($get['custom_id']);
        $this->success('自定义模板数据', $decorate);
    }

    // 富文本详情
    public function richtext()
    {
        $id = $this->request->get('id');
        $data = \addons\shopro\model\Richtext::get(['id' => $id]);
        $this->success($data->title, $data);
    }

    // 同步前端所有页面链接
    public function asyncPages()
    {
        $post = $this->request->post();
        $newLink = $post['data'];
        $existLink = (array)Db::name('shopro_link')->select();
        $newLinkPath = array_column($newLink, 'path');
        $existLinkPath = array_flip(array_column($existLink, 'path'));
        $insertData = [];
        $count = 1;
        foreach ($newLinkPath as $key => $item) {
            if (!isset($existLinkPath[$item]) && isset($newLink[$key]['meta']['async']) && $newLink[$key]['meta']['async']) {
                $insertData[] = [
                    'name' => isset($newLink[$key]['meta']['title']) ? $newLink[$key]['meta']['title'] : '新链接' . $count,
                    'path' => $item,
                    'group' => isset($newLink[$key]['meta']['group']) ? $newLink[$key]['meta']['group'] : '其它',
                    'createtime' => time(),
                    'updatetime' => time()
                ];
                $count++;
            }
        }
        if ($insertData !== []) {
            Db::name('shopro_link')->insertAll($insertData);
        }
    }


    /**
     * 上传文件
     * @ApiMethod (POST)
     * @param File $file 文件流
     */
    public function upload()
    {
        $file = $this->request->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }

        //判断是否已经存在附件
        $sha1 = $file->hash();

        $upload = FaConfig::get('upload');

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix && preg_match("/^[a-zA-Z0-9]+$/", $suffix) ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //禁止上传PHP和HTML文件
        if (in_array($fileInfo['type'], ['text/x-php', 'text/html']) || in_array($suffix, ['php', 'html', 'htm'])) {
            $this->error(__('Uploaded file format is limited'));
        }
        //验证文件后缀
        if (
            $upload['mimetype'] !== '*' &&
            (!in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr))))
        ) {
            $this->error(__('Uploaded file format is limited'));
        }
        //验证是否为图片文件
        $imagewidth = $imageheight = 0;
        if (in_array($fileInfo['type'], ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = getimagesize($fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                $this->error(__('Uploaded file is not a valid image'));
            }
            $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
            $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
        }

        // 文件 md5
        $fileMd5 = md5_file($fileInfo['tmp_name']);

        $replaceArr = [
            '{year}' => date("Y"),
            '{mon}' => date("m"),
            '{day}' => date("d"),
            '{hour}' => date("H"),
            '{min}' => date("i"),
            '{sec}' => date("s"),
            '{random}' => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}' => $suffix,
            '{.suffix}' => $suffix ? '.' . $suffix : '',
            '{filemd5}' => $fileMd5,
        ];
        $savekey = $upload['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //

        if (in_array($upload['storage'], ['cos', 'alioss', 'qiniu'])) {     // upyun:又拍云 ，bos:百度BOS，ucloud: Ucloud， 如果要使用这三种，请自行安装插件配置，并将标示填入前面数组，进行测试
            $token_name = $upload['storage'] . 'token';     // costoken, aliosstoken, qiniutoken
            $controller_name = '\\addons\\' . $upload['storage'] . '\\controller\\Index';

            $storageToken[$token_name] = $upload['multipart'] && $upload['multipart'][$token_name] ? $upload['multipart'][$token_name] : '';
            $domain = request()->domain();

            try {
                $uploadCreate = \think\Request::create('foo', 'POST', array_merge([
                    'name' => $fileInfo['name'],
                    'md5' => $fileMd5,
                    'chunk' => 0,
                ], $storageToken));

                // 重新设置跨域允许域名
                $cors = config('fastadmin.cors_request_domain');
                config('fastadmin.cors_request_domain', $cors . ',' . $domain);

                $uploadController = new $controller_name($uploadCreate);
                $uploadController->upload();
            } catch (HttpResponseException $e) {
                $result = $e->getResponse()->getData();
                if (isset($result['code']) && $result['code'] == 0) {
                    $this->error($result['msg']);
                }

                $resultData = $result['data'];
            }
        } else {
            $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);

            if ($splInfo) {
                $resultData = [
                    'url' => $uploadDir . $splInfo->getSaveName(),
                    'fullurl' => request()->domain() . $uploadDir . $splInfo->getSaveName()
                ];
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        }

        $params = array(
            'admin_id' => 0,
            'user_id' => (int)$this->auth->id,
            'filename'    => substr(htmlspecialchars(strip_tags($fileInfo['name'])), 0, 100),
            'filesize' => $fileInfo['size'],
            'imagewidth' => $imagewidth,
            'imageheight' => $imageheight,
            'imagetype' => $suffix,
            'imageframes' => 0,
            'mimetype' => $fileInfo['type'] == 'application/octet-stream' && in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp']) ? 'image/' . $suffix : $fileInfo['type'],
            'url' => $resultData['url'],
            'uploadtime' => time(),
            'storage' => $upload['storage'],
            'sha1' => $sha1,
        );
        $attachment = new \app\common\model\Attachment;
        $attachment->data(array_filter($params));
        $attachment->save();
        \think\Hook::listen("upload_after", $attachment);
        $this->success(__('Upload successful'), $resultData);
    }


    public function debugLog()
    {
        $params = $this->request->post();
        \think\Log::write($params, 'Frontend-Debug');
    }



}
