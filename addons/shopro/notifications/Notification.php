<?php

namespace addons\shopro\notifications;

use addons\shopro\exception\Exception;
use think\queue\ShouldQueue;
use addons\shopro\model\NotificationConfig;
use addons\shopro\library\notify\channel\Database;
use addons\shopro\library\notify\channel\Sms;
use addons\shopro\library\notify\channel\WxMiniProgram;
use addons\shopro\library\notify\channel\WxOfficeAccount;
use addons\shopro\library\notify\channel\WxOfficeAccountBizsend;
use addons\shopro\library\notify\channel\Email;
use addons\shopro\model\UserOauth;
use addons\shopro\model\Config;
/**
 * 消息通知基类
 */
class Notification
{

    public $notifiableType = 'user';

    public $config = null;

    public function initConfig() {
        // 缓存 5 分钟
        $notificationConfig = NotificationConfig::cache(300)->where('event', $this->event)->select();

        $this->config = array_column($notificationConfig, null, 'platform');
    }


    // 返回发送方式
    public function via($notifiable) {
        $channel = [Database::class];

        if (isset($this->config['sms']) && $this->config['sms']['status']) {
            $channel[] = Sms::class;
        }

        if (isset($this->config['email']) && $this->config['email']['status']) {
            $channel[] = Email::class;
        }

        if (isset($this->config['wxOfficialAccount']) && $this->config['wxOfficialAccount']['status']) {
            $channel[] = WxOfficeAccount::class;
        }

        if (isset($this->config['wxOfficialAccountBizsend']) && $this->config['wxOfficialAccountBizsend']['status']) {
            $channel[] = WxOfficeAccountBizsend::class;
        }

        if (isset($this->config['wxMiniProgram']) && $this->config['wxMiniProgram']['status']) {
            $channel[] = WxMiniProgram::class;
        }

        return $channel;
    }


    // 格式化模板数据
    public function formatParams($params, $type) {
        $paramsData = $params['data'] ?? [];

        $currentConfig = $this->config[$type];

        $newData = [];

        // 有配置，并且开启状态
        if (isset($currentConfig) && $currentConfig['status']) {
            if ($type == 'email') {
                $newContent = $currentConfig['content'];
                if (preg_match_all("/(?<=(p:{)).+?(?=})/", $currentConfig['content'], $matches)) {
                    foreach ($matches[0] as $k => $field) {
                        $fieldVal = $paramsData[$field] ?? '';
                        $newContent = str_replace("p:{" . $field . "}", $fieldVal, $newContent);
                    }
                }

                $params['content'] = $newContent;
            } else {
                $content_arr = $currentConfig['content_arr'];

                if (isset($content_arr['template_id']) && isset($content_arr['fields'])) {
                    if (in_array($type, ['wxOfficialAccountBizsend', 'wxOfficialAccount', 'wxMiniProgram', 'sms'])) {
                        $params['template_id'] = $content_arr['template_id'];
                    }

                    foreach ($content_arr['fields'] as $key => $data) {
                        // 用户填写了才处理，没填的字段直接 pass
                        if (isset($data['template_field']) && $data['template_field']) {
                            if (isset($data['field'])) {
                                $value = $paramsData[$data['field']] ?? '-';
                            } else {
                                $value = $data['value'];
                            }
                            $value = $value ?: '-';

                            $value = $this->substrParams($data['template_field'], $value, $type);

                            if (in_array($type, ['wxMiniProgram', 'wxOfficialAccountBizsend'])) {
                                $newData[$data['template_field']] = ['value' => $value];
                            } else {
                                $newData[$data['template_field']] = $value;
                            }
                        }
                    }
                }

                $params['data'] = $newData;
            }
        }

        return $params;
    }


    // 裁剪参数
    public function substrParams($key, $value, $type) {
        if ($type == 'sms') {
            $value = mb_substr($value, 0, 18);
        } else if (in_array($type, ['wxMiniProgram', 'wxOfficialAccountBizsend'])) {
            $value = $this->substrMiniParams($key, $value);
        }

        return $value;
    }


    // 小程序裁剪参数
    private function substrMiniParams($key, $value) {
        switch(true) {
            case strpos($key, 'thing') !== false;           // 事物
                $value = mb_substr($value, 0, 20);
                break;
            case strpos($key, 'number') !== false;          // 数字
                $value = mb_substr($value, 0, 32);
                break;
            case strpos($key, 'letter') !== false;          // 字母
                $value = mb_substr($value, 0, 32);
                break;
            case strpos($key, 'symbol') !== false;          // 符号
                $value = mb_substr($value, 0, 5);
                break;
            case strpos($key, 'character_string') !== false;// 字符串
                $value = mb_substr($value, 0, 32);
                break;
            case strpos($key, 'phone_number') !== false;    // 电话	
                $value = mb_substr($value, 0, 17);
                break;
            case strpos($key, 'car_number') !== false;      // 车牌	
                $value = mb_substr($value, 0, 8);
                break;
            case strpos($key, 'name') !== false;            // 姓名	,这个地方只能是汉字和字母的组合
                $value = mb_substr($value, 0, 10);
                break;
            case strpos($key, 'phrase') !== false;          // 汉字	
                $value = mb_substr($value, 0, 5);
                break;
        }

        return $value;
    }



    /**
     * 发送成功
     */
    public function sendOk($platform) {
        // 更新发送条数
        NotificationConfig::where('event', $this->event)->where('platform', $platform)->setInc('sendnum');
    }

    /**
     * 默认返回
     */
    public function toArray() {
        return [];
    }


    /**
     * 设置延迟时间
     */
    public function delay($second = 0) {
        if (!($this instanceof ShouldQueue)) {
            new Exception("该消息类型不支持队列，请先继承队列");
        }
        $this->delay = $second;

        return $this;
    }


    /**
     * 获取微信授权 oauth
     */
    protected function getWxOauth($notifiable, $platform) {
        $oauth = UserOauth::where('user_id', $notifiable['id'])
                ->where('provider', 'Wechat')
                ->where('platform', $platform)->find();
        
        if ($oauth && $oauth->openid) {
            return $oauth;
        }

        return null;
    }


    /**
     * 获取拼接域名的地址
     */
    protected function getH5DomainUrl($path) {
        $url = $path;
        if ($domain = $this->getH5Domain()) {
            $domain = rtrim($domain, '/');
            $url = $domain . "/?page=" . urlencode($path);
        }

        return $url;
    }


    protected function getH5Domain() {
        $domain = null;
        // 查询商城配置获取 h5 网址，并缓存 5 分钟
        $config = Config::where('name', 'shopro')->cache(300)->find();
        if ($config && $cv = json_decode($config['value'], true)) {
            $domain = isset($cv['domain']) ? $cv['domain'] : null;
        }

        return $domain;
    }



    /**
     * 获取拼接的小程序地址
     */
    protected function getMiniDomainUrl($path) {
        return "pages/index/index?page=" . urlencode($path);
    }
}
