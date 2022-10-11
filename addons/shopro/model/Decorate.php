<?php

namespace addons\shopro\model;

use addons\shopro\model\DecorateContent;
use think\Model;
use addons\shopro\exception\Exception;

/**
 * 模板装修
 */
class Decorate extends Model
{

    // 表名,不含前缀
    protected $name = 'shopro_decorate';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';

    protected $hidden = ['createtime', 'updatetime', 'deletetime'];
    // 追加属性
    protected $append = [

    ];


    //获取当前平台模板装修数据
    public static function getCurrentPlatformDecorate($platform = '', $id = 0)
    {
        $where = ['deletetime' => null];
        if ($id !== 0) {
            $where['id'] = $id;
        }
        if ($platform == 'preview') {
            $decorate = self::where($where)->order('id asc')->find();
        }else{
            $where['status'] = 'normal';
            $where['type'] = 'shop';
            $decorate = self::where('find_in_set(:platform,platform)', ['platform' => $platform])->where($where)->order('id asc')->find();
        }

        if (!$decorate) {
            new Exception('未找到模板');
        }

        $template = self::getDecorateContent($decorate);
        return $template;
    }

    public static function getCustomDecorate($id)
    {
        $decorateContent = DecorateContent::all(['decorate_id' => $id]);
        foreach ($decorateContent as &$v) {
            $v['content'] = self::getDecorateContentByType($v);
        }
        return $decorateContent;
    }

    public static function asyncDecorateScreenShot($params)
    {
        $decorate = self::get($params['shop_id']);
        if ($decorate) {
            $decorate->image = $params['image'];
            $decorate->save();

        }
    }

    public static function getDecorateContent($decorate)
    {
        $template['home'] = [];           //首页
        $template['user'] = [];           //个人中心
        $template['tabbar'] = [];         //底部菜单
        $template['popup'] = [];          //弹出提醒
        $template['float-button'] = [];   //悬浮按钮
        $decorateContent = DecorateContent::all(['decorate_id' => $decorate->id]);
        foreach ($decorateContent as $k => $v) {
            $v['content'] = self::getDecorateContentByType($v);
            $template[$v->category][] = $v;
        }
        return $template;
    }

    public static function getDecorateContentByType($templateValue)
    {
        $content = json_decode($templateValue->content, true);
        switch ($templateValue['type']) {
            case 'search':
            case 'coupons':
            case 'live':
            case 'seckill':
            case 'groupon':
            case 'wallet-card':
            case 'order-card':
            case 'rich-text':
            case 'nav-bg':
                break;
            case 'banner':
            case 'menu':
            case 'adv':
            case 'grid-list':
            case 'popup':
            case 'nav-list':
                foreach ($content['list'] as &$l) {
                    $l['image'] && $l['image'] = cdnurl($l['image'], true);
                }
                break;
            case 'user':
            case 'title-block':
            case 'goods-list':
            case 'goods-group':
                $content['image'] && $content['image'] = cdnurl($content['image'], true);
                break;
            case 'tabbar':
                if ($content['style'] < 3) {
                    foreach ($content['list'] as &$l) {
                        $l['image'] && $l['image'] = cdnurl($l['image'], true);
                        $l['activeImage'] && $l['activeImage'] = cdnurl($l['activeImage'], true);
                    }
                }
                break;
            case 'float-button':
                $content['image'] = cdnurl($content['image'], true);
                foreach ($content['list'] as &$l) {
                    if ($l['style'] == 1) {
                        $l['image'] && $l['image'] = cdnurl($l['image'], true);
                    }
                    $l['btnimage'] && $l['btnimage'] = cdnurl($l['btnimage'], true);
                }
        }
        return $content;
    }
}