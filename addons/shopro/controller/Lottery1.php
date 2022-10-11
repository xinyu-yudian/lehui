<?php

namespace addons\shopro\controller;

use addons\shopro\exception\Exception;
use addons\shopro\model\User;
use think\Env;
use think\Db;

class Lottery extends Base
{

    protected $noNeedLogin = ['start_lottery'];
    protected $noNeedRight = ['*'];

    public function index()
    {

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
        foreach ($lottery as $k => $v1) {
            $lottery[$k]['image'] = 'https://lehuicc.oss-cn-guangzhou.aliyuncs.com' . $v1['image'];
            $lottery[$k]['timecount'] = $v1['endtime'] - time();
            if (in_array($v1['id'], $user_join_ids_convert)) {
                $lottery[$k]['isJoin'] = 1;
            } else {
                $lottery[$k]['isJoin'] = 2;
            }
        }
        $this->success('活动列表', $lottery);
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
            $lottery_detail['is_join'] = 1;//参与过
        } else {
            $lottery_detail['is_join'] = 2;//未参与
        }
        if ($lottery_detail['endtime'] < time()) {
            $lottery_detail['is_join'] = 3;//已经结束
        }
        $images = explode(',', $lottery_detail['images']);
        $images_ = [];
        foreach ($images as $v) {
            array_push($images_, 'https://lehuicc.oss-cn-guangzhou.aliyuncs.com' . $v);
        }
        $lottery_detail['images'] = $images_;
        $lottery_detail = json_decode(json_encode($lottery_detail), true);
        if ($lottery_detail) {
            $this->success('活动详情', $lottery_detail);
        } else {
            $this->error("非法操作");
        }
    }

    public function join_lottery()
    {
        $l_id = $this->request->get('id');
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

        $data['user_id'] = $user_id;
        $data['lottery_id'] = $l_id;
        $data['createtime'] = time();

        $res = Db::name('join_lottery_userlist')->insert($data);
        if ($res) {
            $this->success('参与活动成功');
        }

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
        //参与抽奖总人数
        $user_list_count = Db::name('join_lottery_userlist')
            ->where([
                'lottery_id' => $l_id,
                'is_award' => 2,
            ])
            ->count();

        $user_list = Db::name('join_lottery_userlist')
            ->where([
                'lottery_id' => $l_id,
            ])
            ->select();

        //奖品初始化
        $prizeids = explode(',', $lottery['awardlist']);
        $prize = [];
        foreach ($prizeids as $v){
            $award = Db::name('award')
                ->where([
                    'id' => $v,
                    'deletetime' => null,
                    'lottery_id' => $l_id
                ])
                ->find();
            array_push($prize,$award);
        }
        dump($prize);



//        $prize = Db::name('award')
//            ->where([
//                'lottery_id' => $l_id,
//                'is_award' => 2,
//            ])
//            ->select();

        // $user_list  参与活动的人list
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
                $user_list[$k]['is_award_n'] = 0;//之前没有中过将
            }else {
                $user_list[$k]['is_award_n'] = 1;//之前中奖了
            }



        }


    }


}
