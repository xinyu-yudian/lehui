<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use addons\shopro\model\User;
use think\Cache;
use think\Env;
use think\Db;

class Lottery extends Base
{

    protected $noNeedLogin = ['need_join_nologin'];
    protected $noNeedRight = ['*'];

    public function index()
    {
    }

    public function need_join_nologin()
    {
        //$old_buy_special = Db::name('shopro_special_log')->where('id',1)->find();
        $nowtime = time();
        $lottery = Db::name('lottery')->field('image,title,introduce')
            ->where([
                'endtime' => ['>', $nowtime],
            ])
            ->find();
        $this->success('success', $lottery);
    }

    public function need_join()
    {
        //$old_buy_special = Db::name('shopro_special_log')->where('id',1)->find();

        $user = $this->auth->getUser();
        $user_id = $user['id'];
        $user_join_ids = Db::name('join_lottery_userlist')->field('lottery_id')->where('user_id', $user_id)->select();
        $user_join_ids_convert = [];
        foreach ($user_join_ids as $v) {
            array_push($user_join_ids_convert, $v['lottery_id']);
        }
        $nowtime = time();
        $not_join_lottery = Db::name('lottery')->field('image,title,introduce')
            ->where([
                'id' => ['not in', $user_join_ids_convert],
                'endtime' => ['>', $nowtime],
            ])
            ->find();
        $this->success('未参加的活动', $not_join_lottery);
    }


    public function lottery_list()
    {
        //$old_buy_special = Db::name('shopro_special_log')->where('id',1)->find();
        //找没过期的活动 ，如果参加过显示已经参与请等待开奖结果

        $user = $this->auth->getUser();
        $user_id = $user['id'];
        $user_join_ids = Db::name('join_lottery_userlist')->field('lottery_id')->where('user_id', $user_id)->select();
        $user_join_ids_convert = [];
        foreach ($user_join_ids as $v) {
            array_push($user_join_ids_convert, $v['lottery_id']);
        }
        $nowtime = time();
        $lottery = Db::name('lottery')
            ->where([
                'endtime' => ['>', $nowtime],
            ])
            ->select();
        $lotteryids = [];
        $lists = [];
        foreach ($lottery as $k => $v1) {
            $v1['image'] = 'https://lehuicc.oss-cn-guangzhou.aliyuncs.com' . $v1['image'];
            $v1['timecount'] = $v1['endtime'] - time();
            if (in_array($v1['id'], $user_join_ids_convert)) {
                $v1['isJoin'] = 1;
            } else {
                $v1['isJoin'] = 2;
            }
            $lists[] = $v1;
            $lotteryids[] = $v1['id'];
        }
        // 获取已经参与的列表
        $joinids = [];
        foreach ($user_join_ids_convert as $k => $v) {
            if (!in_array($v, $lotteryids)) {
                $joinids[] = $v;
            }
        }
        if (count($joinids) > 0) {
            $lottery = Db::name('lottery')
                ->where([
                    'id' => ['in', $joinids],
                ])
                ->select();
            foreach ($lottery as $k => $v1) {
                $v1['image'] = 'https://lehuicc.oss-cn-guangzhou.aliyuncs.com' . $v1['image'];
                $v1['timecount'] = $v1['endtime'] - time();
                $v1['isJoin'] = 1;
                $lists[] = $v1;
            }
        }
        $this->success('活动列表', $lists);
    }

    public function lottery_detail()
    {
        $id = $this->request->get('id');
        //查询是否参与过本活动的抽奖
        $user = $this->auth->getUser();
        $user_id = $user['id'];
        $is_join = Db::name('join_lottery_userlist')->where([
            'user_id' => $user_id,
            'lottery_id' => $id,
        ])->find();
        $lottery_detail = Db::name('lottery')->where('id', $id)->find();
        if ($is_join) {
            $lottery_detail['is_join'] = 1; //参与过
            //如果参与过查看是否中奖
            if ($is_join['is_award'] == 1) {
                $lottery_detail['win'] = 1; //中奖了
                $win_award = Db::name('award')->where([
                    'id' => $is_join['award_id'],
                ])->find();
                $lottery_detail['win_prize'] = $win_award;
            } else if ($is_join['is_award'] == 0) {
                $lottery_detail['win'] = 0; //没中奖
            } else {
                $lottery_detail['win'] = 3; //未开奖
            }
        } else {
            $lottery_detail['is_join'] = 2; //未参与
            $lottery_detail['win'] = 3; //没参与
        }
        if ($lottery_detail['endtime'] < time()) {
            $lottery_detail['is_join'] = 3; //已经结束
        }
        $images = explode(',', $lottery_detail['images']);
        $images_ = [];
        foreach ($images as $v) {
            array_push($images_, 'https://lehuicc.oss-cn-guangzhou.aliyuncs.com' . $v);
        }
        $lottery_detail['images'] = $images_;
        $lottery_detail['image'] = 'https://lehuicc.oss-cn-guangzhou.aliyuncs.com' . $lottery_detail['image'];
        $award_list = Db::name('award')->where('lotterylist', $id)->select();
        $lottery_detail['award_list'] = $award_list;
        $lottery_detail['users'] = Db::table('fa_join_lottery_userlist')->where(['lottery_id' => $id])->count();
        $lottery_detail = json_decode(json_encode($lottery_detail), true);
        if ($lottery_detail) {
            $this->success('活动详情', $lottery_detail);
        } else {
            $this->error("非法操作");
        }
    }









    
    public function join_lottery()
    {
     //推送订阅消息
    $subscribe = $this->request->get('subscribe');
    if($subscribe == "accept"){
        halt($subscribe);
    }









        $l_id = $this->request->get('id');
        $is_push = $this->request->get('push');
        $user = $this->auth->getUser();
        $user_id = $user['id'];
        $history_join = Db::name('join_lottery_userlist')->where([
            'user_id' => $user_id,
            'lottery_id' => $l_id,
        ])->find();
        if ($history_join) {
            $this->error("您已经参与过此活动");
        }
        $nowtime = time();
        $lottery_detail = Db::name('lottery')->where('id', $l_id)->find();
        if ($lottery_detail['endtime'] < $nowtime) {
            $this->error("该抽奖活动已经结束");
        }

        $money = Db::table('fa_lottery')->where(['id' => $l_id])->find();
        if ($money['user_cost'] > 0) {
            $user_cost = Db::table('fa_shopro_order')->where([
                'goods_amount' => ['>=', $money['user_cost']],
                'user_id' => $user_id,
                'createtime' => ['>=', $money['createtime']],
                'status' => ['>=', 1]
            ])->find();
            if (!$user_cost) {
                $msg = "您需要单笔订单消费满".$money['user_cost']."元才可以参与抽奖";
                $this->error($msg);
            }
        }

        $data['user_id'] = $user_id;
        $data['lottery_id'] = $l_id;
        $data['createtime'] = time();
        $data['is_push'] = $is_push;

        $res = Db::name('join_lottery_userlist')->insert($data);
        //统计该活动已报名人数，插入redis
        $user_count=Db::table('fa_join_lottery_userlist')->where(['lottery_id'=>$l_id])->count();
        Cache::store('redis')->set('lotterys' . $l_id, $user_count);

        if ($res) {
            $this->success('参与活动成功');
        }
    }

    public function award_list()
    {
        //$old_buy_special = Db::name('shopro_special_log')->where('id',1)->find();
        //找没过期的活动 ，如果参加过显示已经参与请等待开奖结果

        $id = $this->request->get('lottery_id');
        $user_join_ids = Db::name('award')->where('lotterylist', $id)->select();


        $this->success('活动列表', $user_join_ids);
    }


    public function start_lottery()
    {
        $l_id = $this->request->get('lottery_id');
        $nowtime = time();
        $lottery = Db::name('lottery')
            ->where([
                'id' => $l_id,
                'endtime' => ['<', $nowtime],
                'ifstart' => 0,
            ])
            ->find();
        if (!$lottery) {
            $this->error("错误");
        }

        $user_list = Db::name('join_lottery_userlist')
            ->where([
                'lottery_id' => $l_id,
            ])
            ->select();

        //奖品初始化
        $prize = Db::name('award')
            ->where([
                'deletetime' => null,
                'lotterylist' => $l_id
            ])
            ->select();
        //总的奖品数量
        $total_prize_num = 0;
        foreach ($prize as $v) {
            $total_prize_num += $v['nownum'];
        }

        // $user_list  参与活动的人list
        //n天没有中过奖 的人的数量
        $no_award_num = 0;
        foreach ($user_list as $k => $v) {
            //
            $user_invite_count = Db::name('invite_user')
                ->where([
                    'lottery_id' => $l_id,
                    'invite_user_id' => $v['user_id'],
                ])
                ->count();
            //参与此活动邀请的人数
            $user_list[$k]['invite_count'] = $user_invite_count;
            //之前n天是否中奖
            if ($nowtime - $v['createtime'] > 30 * 86400) {
                $no_award_num += 1;
                $user_list[$k]['is_award_n'] = 0; //之前没有中过将
            } else {
                $user_list[$k]['is_award_n'] = 1; //之前中奖了
            }
        }
    }
}
