<?php

namespace addons\alisms\controller;

use think\addons\Controller;

/**
 * 阿里短信
 */
class Index extends Controller
{

    protected $model = null;
    protected $templateList = [
        'register'     => '注册',
        'resetpwd'     => '重置密码',
        'changepwd'    => '修改密码',
        'changemobile' => '修改手机号',
        'profile'      => '修改个人信息',
        'notice'       => '通知',
        'mobilelogin'  => '移动端登录',
        'bind'         => '绑定账号',
    ];

    public function _initialize()
    {
        if (!\app\admin\library\Auth::instance()->id) {
            $this->error('暂无权限浏览');
        }
        parent::_initialize();
    }

    //首页
    public function index()
    {
        $this->view->assign('templateList', $this->templateList);
        return $this->view->fetch();
    }

    //发送测试短信
    public function send()
    {
        $config = get_addon_config('alisms');
        $mobile = $this->request->post('mobile');
        $template = $this->request->post('template');
        $sign = $this->request->post('sign', '');

        if (!$mobile) {
            $this->error('手机号不能为空');
        }

        $templateArr = $config['template'] ?? [];
        if (!isset($templateArr[$template]) || !$templateArr[$template]) {
            $this->error('后台未配置对应的模板CODE');
        }
        $template = $templateArr[$template];
        $sign = $sign ? $sign : $config['sign'];
        $param = (array)json_decode($this->request->post('param', '', 'trim'));
        $param = ['code' => mt_rand(1000, 9999)];
        $alisms = new \addons\alisms\library\Alisms();
        $ret = $alisms->mobile($mobile)
            ->template($template)
            ->sign($sign)
            ->param($param)
            ->send();
        if ($ret) {
            $this->success("发送成功");
        } else {
            $this->error("发送失败！失败原因：" . $alisms->getError());
        }
    }

}
