<?php

namespace addons\shopro\controller;

use think\Db;
use app\common\library\Sms;
use fast\Random;
use think\Validate;
use addons\shopro\library\Wechat;
use addons\shopro\model\UserOauth;
use addons\shopro\model\User as UserModel;
use addons\shopro\model\UserStore;

/**
 * 会员接口
 */
class User extends Base
{
    protected $noNeedLogin = ['accountLogin', 'smsLogin', 'register', 'forgotPwd', 'wxMiniProgramOauth', 'getWxMiniProgramSessionKey', 'wxOfficialAccountOauth', 'wxOfficialAccountBaseLogin', 'wxOpenPlatformOauth', 'appleIdOauth', 'logout'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        return parent::_initialize();
    }

    /**
     * 会员中心
     */
    public function index()
    {
        $auth = \app\common\library\Auth::instance();
        $auth->setAllowFields(['id', 'username', 'nickname', 'mobile', 'avatar', 'score', 'birthday', 'money', 'group', 'group_id', 'verification', 'child_user_count', 'child_user_count_1', 'child_user_count_2', 'total_consume']);
        $data = $auth->getUserinfo();
        $data['avatar'] = $data['avatar'] ? cdnurl($data['avatar'], true) : '';
        if (!isset($data['group'])) {
            $data['group'] = \addons\shopro\model\UserGroup::get($data['group_id']);
        }

        $this->success('用户信息', $data);
    }


    /**
     * 获取用户数据
     *
     * @return void
     */
    public function userData()
    {
        $user = $this->auth->getUserinfo();
        // 查询用户优惠券数量
        $userCoupons = \addons\shopro\model\Coupons::getCouponsList(1);
        $data['coupons_num'] = count($userCoupons);

        // 查询用户是否是门店管理员
        $userStores = UserStore::where('user_id', $user['id'])->select();
        $data['is_store'] = $userStores ? 1 : 0;
        $data['store_id'] = 0;
        if (count($userStores) == 1) {
            // 只有一个店铺 直接进入店铺
            $data['store_id'] = $userStores[0]['store_id'];
        }

        // 订单数量
        $data['order_num'] = \addons\shopro\model\Order::statusNum();

        $this->success('用户数据', $data);
    }

    /**
     * 1.账号登录
     *
     * @param string $account 账号
     * @param string $password 密码
     */
    public function accountLogin()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['token' => $this->auth->getToken()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 2.短信登录
     *
     * @param string $mobile 手机号
     * @param string $code 验证码
     */
    public function smsLogin()
    {
        $mobile = $this->request->post('mobile');
        $code = $this->request->post('code');
        if (!$mobile || !$code) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (!Sms::check($mobile, $code, 'mobilelogin')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        }
        if ($ret) {
            Sms::flush($mobile, 'mobilelogin');
            $data = ['token' => $this->auth->getToken()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 3.注册会员
     *
     * @param string $mobile 手机号
     * @param string $password 密码
     * @param string $code 验证码
     */
    public function register()
    {
        $username = $this->request->post('mobile');
        $password = $this->request->post('password');
        $email = $this->request->post('mobile') . '@' . request()->host();

        $mobile = $this->request->post('mobile');
        $code = $this->request->post('code');
        if (!$password) {
            $this->error(__('请填写密码')); //TODO:密码规则校验
        }
        if (strlen($password) < 6 || strlen($password) > 16) {
            $this->error(__('密码长度 6-16 位')); //TODO:密码规则校验
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('邮箱填写错误'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号填写错误'));
        }
        $ret = Sms::check($mobile, $code, 'register');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        $extend = $this->getUserDefaultFields();
        $ret = $this->auth->register($username, $password, $email, $mobile, $extend);
        if ($ret) {
            $user = $this->auth->getUser();
            $user->nickname = $user->nickname . $user->id;
            $verification = $user->verification;
            $verification->mobile = 1;
            $user->verification = $verification;
            $user->save();
            $data = ['token' => $this->auth->getToken()];
            $this->success(__('注册成功'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 4.忘记密码
     *
     * @param string $mobile 手机号
     * @param string $password 新密码
     * @param string $code 验证码
     */
    public function forgotPwd()
    {
        $mobile = $this->request->post("mobile");
        $newpassword = $this->request->post("password");
        $captcha = $this->request->post("code");
        if (!$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (strlen($newpassword) < 6 || strlen($newpassword) > 16) {
            $this->error(__('密码长度 6-16 位')); //TODO:密码规则校验
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if (!$user) {
            $this->error(__('User not found'));
        }
        $ret = Sms::check($mobile, $captcha, 'resetpwd');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        Sms::flush($mobile, 'resetpwd');
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 5.绑定手机号
     *
     * @param string $mobile 手机号
     * @param string $code 验证码
     */
    public function bindMobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('code');
        if (!$mobile || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Mobile is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exists'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success(__('Mobile is binded'));
    }


    /**
     * 6.修改密码
     *
     * @param string $oldpassword 手机号
     * @param string $newpassword 验证码
     */
    public function changePwd()
    {
        $user = $this->auth->getUser();

        $oldpassword = $this->request->post("oldpassword");
        $newpassword = $this->request->post("newpassword");

        if (!$newpassword || !$oldpassword) {
            $this->error(__('Invalid parameters'));
        }
        if (strlen($newpassword) < 6 || strlen($newpassword) > 16) {
            $this->error(__('密码长度 6-16 位')); //TODO:密码规则校验
        }

        $ret = $this->auth->changepwd($newpassword, $oldpassword);

        if ($ret) {
            $this->auth->direct($user->id);
            $data = ['userinfo' => $this->auth->getUserinfo()];

            $this->success(__('Change password successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 获取微信小程序session_key
     *
     * @param string  $code       加密code
     * @param boolean $autoLogin  是否自动登录(需已注册会员)
     */
    public function getWxMiniProgramSessionKey()
    {
        $post = $this->request->post();
        $autoLogin = $post['autoLogin'];
        $wechat = new Wechat('wxMiniProgram');
        $decryptSession = $wechat->code($post['code']);
        if (!isset($decryptSession['session_key'])) {
            $this->error('未获取session_key,请重启应用');
        }
        \think\Cache::set($decryptSession['session_key'], $decryptSession, 24 * 3600 * 31); // 强制31天过期
        $userOauth = UserOauth::get([
            'provider' => 'Wechat',
            'platform' => 'wxMiniProgram',
            'openid' => $decryptSession['openid'],
            'user_id' => ['neq', 0]
        ]);
        if ($userOauth) {
            $userOauth->save(['session_key' => $decryptSession['session_key']]);
        }
        if ($autoLogin && $userOauth) {
            $ret = $this->auth->direct($userOauth->user_id);
            if ($ret) {
                $token = $this->auth->getToken();
                $this->success(__('Logged in successful'), ['token' => $token, 'session_key' => $decryptSession['session_key'], 'openid' => $decryptSession['openid']]);
            } else {
                $this->error($this->auth->getError());
            }
        }

        $this->success('', $decryptSession);
    }
    /**
     * 微信小程序登录
     *
     * @param string  $session_key      session_key
     * @param string  $signature        校验签名
     * @param string  $iv               解密向量
     * @param string  $encryptedData    需解密完整用户信息
     * @param boolean $refresh          重新获取或刷新最新的用户信息 (用户头像失效或微信客户端修改昵称等情况)
     */
    public function wxMiniProgramOauth()
    {
        $post = $this->request->post();

        $token = Db::transaction(function () use ($post) {
            try {
                $wechat = new Wechat('wxMiniProgram');
                $decryptSession = \think\Cache::get($post['session_key']);
                if (!$decryptSession || !isset($decryptSession['openid'])) {
                    throw \Exception('未获取到登录态,请重试');
                }
                $decryptUserInfo = $wechat->decryptData($post['session_key'], $post['iv'], $post['encryptedData']); // 客户端传值数据都不可信，需服务端解密用户信息
                $decryptUserInfo = array_merge($decryptUserInfo, $decryptSession);
                //组装decryptData
                $decryptData = array_change_key_case($decryptUserInfo, CASE_LOWER);
                if (empty($decryptData['openid'])) {
                    throw \Exception('code错误,请重试');
                }
                $decryptData['headimgurl'] = $decryptData['avatarurl'];
                $decryptData['sex'] = $decryptData['gender'];
                $decryptData['session_key'] = $post['session_key'];
                return $this->oauthLoginOrRegisterOrBindOrRefresh($post['event'], $decryptData, 'wxMiniProgram', 'Wechat');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        });

        if ($token) {
            $this->success(__('Logged in successful'), ['token' => $token]);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 微信APP登录
     *
     * @param string $authResult 授权信息
     */
    public function wxOpenPlatformOauth()
    {
        $post = $this->request->post();

        $token = Db::transaction(function () use ($post) {
            try {
                //组装decryptData
                $authResult = $post['authResult'];
                $res = \fast\Http::get('https://api.weixin.qq.com/sns/userinfo?access_token=' . $authResult['access_token'] . '&openid=' . $authResult['openid']);
                $userInfo = json_decode($res, true);
                if (isset($userInfo['errmsg'])) {
                    throw \Exception($userInfo['errmsg']);
                }
                $decryptData = array_merge($userInfo, $authResult);
                return $this->oauthLoginOrRegisterOrBindOrRefresh($post['event'], $decryptData, 'App', 'Wechat');
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        });

        if ($token) {
            $this->success(__('Logged in successful'), ['token' => $token]);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 微信公众号登录、更新信息、绑定(授权页 非api)
     *
     * @param string $code 加密code
     */
    public function wxOfficialAccountOauth()
    {
        $token = '';
        $params = $this->request->get();

        $payload = json_decode(htmlspecialchars_decode($params['payload']), true);
        // 解析前端主机
        if ($payload['event'] !== 'login' && $payload['token'] !== '') {
            $this->auth->init($payload['token']);
        }

        $wechat = new Wechat('wxOfficialAccount');
        $oauth = $wechat->oauth();
        $decryptData = $oauth->user()->getOriginal();
        $token = Db::transaction(function () use ($payload, $decryptData) {
            try {
                $token = $this->oauthLoginOrRegisterOrBindOrRefresh($payload['event'], $decryptData, 'wxOfficialAccount', 'Wechat');
                return $token;
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        });
        // 跳转回前端
        if ($token) {
            header('Location: ' . $payload['host']  . 'pages/public/loading/?token=' . $token);
        } else {
            header('Location: ' . $payload['host']);
        }
        exit;
    }

    /**
     * 苹果ID授权
     *
     * @param string $authResult 授权信息
     * @param string $userInfo 用户信息
     */
    public function appleIdOauth()
    {
        $post = $this->request->post();
        $userInfo = $post['userInfo'];
        $token = '';
        $platform = request()->header('platform');

        try {
            \think\Loader::addNamespace('AppleSignIn', ADDON_PATH . 'shopro' . DS . 'library' . DS . 'apple-signin' . DS);
            $identityToken = $userInfo['identityToken'];
            $clientUser = $userInfo['openId'];
            $appleSignInPayload = \AppleSignIn\ASDecoder::getAppleSignInPayload($identityToken);
            $isValid = $appleSignInPayload->verifyUser($clientUser);
            if ($isValid) {
                $nickname = '';
                $headimgurl = '';
                if (isset($userInfo['fullName']['familyName'])) {
                    $nickname = $userInfo['fullName']['familyName'] . ' ' . $userInfo['fullName']['giveName'];
                } else {
                    $nickname = '';
                }
                $decryptData = [
                    'openid' => $userInfo['openId'],
                    'nickname' => $nickname,
                    'access_token' => $userInfo['authorizationCode'],
                    'headimgurl' => $headimgurl
                ];
                $token = $this->oauthLoginOrRegisterOrBindOrRefresh($post['event'], $decryptData, $platform, 'Apple');
            }
        } catch (\Exception $e) {
            $this->error('登录失败:' . $e->getMessage());
        }

        if ($token) {
            $this->success(__('Logged in successful'), ['token' => $token]);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 微信公众号静默登录
     *
     * @param string $code 加密code
     */
    public function wxOfficialAccountBaseLogin()
    {
        $wechat = new Wechat('wxOfficialAccount');
        $oauth = $wechat->oauth();
        $oUrl = input('get.state');
        $url = explode('/', $oUrl);
        $decryptData = $oauth->user()->getOriginal();
        if ($decryptData) {
            header('Location:' . $oUrl . '&openid=' . $decryptData['openid']);
        } else {
            $this->error('未获取到OPENID');
        }
    }

    /**
     * 第三方登录或自动注册或绑定
     *
     * @param string  $event        事件:login=登录, refresh=更新账号授权信息, bind=绑定第三方授权
     * @param array   $decryptData  解密参数
     * @param string  $platform     平台名称
     * @param string  $provider     厂商名称
     * @param int     $keeptime     有效时长
     * @return string $token        返回用户token
     */
    private function oauthLoginOrRegisterOrBindOrRefresh($event, $decryptData, $platform, $provider, $keeptime = 0)
    {
        $oauthData = $decryptData;
        $oauthData = array_merge($oauthData, [
            'provider' => $provider,
            'platform' => $platform,

        ]);
        if ($platform === 'wxMiniProgram' || $platform === 'App') {
            $oauthData['expire_in'] = 7200;
            $oauthData['expiretime'] = time() + 7200;
        }
        $userOauth = UserOauth::where(['openid' => $decryptData['openid'], 'user_id' => ['neq', 0]])->where('platform', $platform)->where('provider', $provider)->lock(true)->find();
        switch ($event) {
            case 'login':               // 登录(自动注册)
                if (!$userOauth) {      // 没有找到第三方登录信息 创建新用户
                    //默认创建新用户
                    $user_id = 0;
                    $createNewUser = true;
                    $oauthData['logintime'] = time();
                    $oauthData['logincount'] = 1;
                    // 判断是否有unionid 并且已存在oauth数据中
                    if (isset($oauthData['unionid'])) {
                        //存在同厂商信息，添加oauthData数据，合并用户
                        $userUnionOauth = UserOauth::get(['unionid' => $oauthData['unionid'], 'provider' => $provider, 'user_id' => ['neq', 0]]);
                        if ($userUnionOauth) {
                            $existUser = $this->auth->direct($userUnionOauth->user_id);
                            if ($existUser) {
                                $createNewUser = false;
                            }
                        }
                    }

                    // 创建空用户
                    if ($createNewUser) {
                        $username = Random::alnum(20);
                        $password = '';
                        $domain = request()->host();
                        $extend = $this->getUserDefaultFields();
                        $extend['nickname'] = $oauthData['nickname'] ? $oauthData['nickname'] : $extend['nickname'];
                        $extend['avatar'] = $oauthData['headimgurl'] ? $oauthData['headimgurl'] : $extend['avatar'];
                        $this->auth->register($username, $password, $username . '@' . $domain, '', $extend, $keeptime);
                        if (empty($oauthData['nickname'])) {
                            $this->auth->getUser()->save(['nickname' => $extend['nickname'] . $this->auth->getUser()->id]);
                        }
                    }
                    $oauthData['user_id'] = $this->auth->getUser()->id;
                    $oauthData['createtime'] = time();
                    UserOauth::strict(false)->insert($oauthData);
                } else {
                    // 找到第三方登录信息，直接登录
                    $user_id = $userOauth->user_id;
                    if ($user_id && $this->auth->direct($user_id) && $this->auth->getUser()) {       // 获取到用户
                        $oauthData['logincount'] = $userOauth->logincount + 1;
                        $oauthData['logintime'] = time();
                        $userOauth->allowField(true)->save($oauthData);
                    } else {         // 用户已被删除 重新执行登录
                        // throw \Exception('此用户已删除');
                        $userOauth->delete();
                        $this->oauthLoginOrRegisterOrBindOrRefresh($event, $decryptData, $platform, $provider);                    }
                }
                break;
            case 'refresh':
                if (!$userOauth) {
                    throw \Exception('未找到第三方授权账户');
                }
                if (!empty($oauthData['nickname'])) {
                    $refreshFields['nickname'] = $oauthData['nickname'];
                }
                if (!empty($oauthData['headimgurl'])) {
                    $refreshFields['avatar'] = $oauthData['headimgurl'];
                }
                $this->auth->getUser()->save($refreshFields);
                $userOauth->allowField(true)->save($oauthData);
                break;
            case 'bind':
                if (!$this->auth->getUser()) {
                    throw \Exception('请先登录');
                }

                $oauthData['user_id'] = $this->auth->getUser()->id;

                if ($userOauth) {
                    if ($userOauth['user_id'] != 0 && $userOauth['user_id'] != $this->auth->getUser()->id && UserModel::get($userOauth['user_id'])) {
                        throw \Exception('该账号已被其他用户绑定');
                    }
                    $oauthData['id'] = $userOauth->id;
                    $userOauth->strict(false)->update($oauthData);
                } else {
                    $oauthData['logincount'] = 1;
                    $oauthData['logintime'] = time();
                    $oauthData['createtime'] = time();
                    UserOauth::strict(false)->insert($oauthData);
                }
                break;
        }
        if ($this->auth->getUser()) {
            $this->setUserVerification($this->auth->getUser(), $provider, $platform);
            return $this->auth->getToken();
        }
        return false;
    }

    /**
     * 第三方用户授权信息
     */
    public function thirdOauthInfo()
    {
        $user = $this->auth->getUser();
        $platform = request()->header('platform');
        $userOauth = UserOauth::where([
            'platform' => $platform,
            'user_id'  => $user->id
        ])->field('headimgurl, nickname')->find();
        $this->success('获取成功', $userOauth);
    }


    /**
     * 解除绑定
     */
    public function unbindThirdOauth()
    {
        $user = $this->auth->getUser();
        $platform = $this->request->post('platform');
        $provider = $this->request->post('provider');

        $verification = $user->verification;
        if (!$verification->mobile) {
            $this->error('请先绑定手机号再进行解绑操作');
        }

        $verifyField = $platform;
        if ($platform === 'App' && $provider === 'Wechat') {
            $verifyField = 'wxOpenPlatform';
        }

        $verification->$verifyField = 0;
        $user->verification = $verification;
        $user->save();
        $userOauth = UserOauth::where([
            'platform' => $platform,
            'provider'  => $provider,
            'user_id' => $user->id
        ])->delete();
        if ($userOauth) {
            $this->success('解绑成功');
        }
        $this->error('解绑失败');
    }

    /**
     * 注销登录
     */
    public function logout()
    {
        if ($this->auth->isLogin()) {
            $this->auth->logout();
        }
        $this->success(__('Logout successful'));
    }


    /**
     * 修改会员个人信息
     *
     * @param string $avatar 头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     * @param string $birthday 生日
     * @param string $bio 个人简介
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio', '');
        $birthday = $this->request->post('birthday');
        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        $user->nickname = $nickname;
        $user->bio = $bio;
        $user->birthday = $birthday;
        if (!empty($avatar)) {
            $user->avatar = $avatar;
        }
        $user->save();
        $this->success();
    }

    private function getUserDefaultFields()
    {
        $userConfig = json_decode(\addons\shopro\model\Config::get(['name' => 'user'])->value, true);
        return $userConfig;
    }

    private function setUserVerification($user, $provider, $platform)
    {
        $verification = $user->verification;
        if ($platform === 'App') {
            $platform = '';
            if ($provider === 'Wechat') {
                $platform = 'wxOpenPlatform';
            } elseif ($provider === 'Alipay') {
                $platform = 'aliOpenPlatform';
            }
        }
        if ($platform !== '') {
            $verification->$platform = 1;
            $user->verification = $verification;
            $user->save();
        }
    }
}
