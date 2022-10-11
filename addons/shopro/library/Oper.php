<?php

namespace addons\shopro\library;

use app\admin\library\Auth as AdminAuth;
use addons\shopro\model\Store;
use addons\shopro\model\User;

class Oper
{
    public static function set($operType = '', $operId = 0)
    {
        if ($operType === '') {
            // 自动获取操作人
            $admin = AdminAuth::instance();     // 没有登录返回的还是这个类实例
            if ($admin->isLogin()) {
                // 后台管理员
                $operType = 'admin';
                $operId = $admin->id;
            } else if (strpos(request()->url(), 'store.store') !== false) {
                // 门店
                $store = Store::info();
                if ($store) {
                    $operType = 'store';
                    $operId = $store['id'];
                }
            } else if (strpos(request()->url(), 'addons/shopro') !== false) {
                // 用户
                $user = User::info();
                if ($user) {
                    $operType = 'user';
                    $operId = $user->id;
                }
            }
        }
        if ($operType === '') {
            $operType = 'system';
        }
        return [
            'oper_type' => $operType,
            'oper_id' => $operId
        ];
    }

    public static function get($operType, $operId)
    {
        $operator = null;
        if ($operType === 'admin') {
            $operator = \app\admin\model\Admin::where('id', $operId)->field('nickname as name, avatar')->find();
            $operator['type'] = '管理员';
        } elseif ($operType === 'user') {
            $operator = \addons\shopro\model\User::where('id', $operId)->field('nickname as name, avatar')->find();
            $operator['type'] = '用户';
        } elseif ($operType === 'store') {
            $operator = \addons\shopro\model\Store::where('id', $operId)->field('name')->find();
            $operator['type'] = '门店';
            $operator['avatar'] = '';
        } else {
            $operator = [
                'name' => '系统',
                'avatar' => '',
                'type' => '系统'
            ];
        }
        if(!isset($operator['name'])) {
            $operator['name'] = '已删除';
            $operator['avatar'] = '';
        }
        return $operator;
    }
}
