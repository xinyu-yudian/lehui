<?php

namespace addons\shopro\model;

use addons\shopro\exception\Exception;
use think\Db;
use think\Model;
/**
 * 用户签到
 */
class UserSign extends Model
{
    protected $name = 'shopro_user_sign';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $hidden = ['createtime', 'updatetime'];

    // 追加属性
    protected $append = [
        
    ];


    // 获取签到记录
    public static function getList ($params) {
        $user = User::info();

        extract($params);
        $month = $month ?? date('Y-m');

        // if ($month > date('Y-m')) {
        //     new Exception('只能查看当前月之前的签到记录');
        // }

        $sign = self::where('user_id', $user->id)->where('date', 'like', $month . '%')->order('date', 'desc')->select();
        $sign_dates = array_column($sign, 'date');

        $totime = time();
        $today = date('Y-m-d');
        // 要查询的是否是当前月
        $is_current = ($month == date('Y-m')) ? true : false;
        // 所选月开始时间戳
        $month_start_time = strtotime($month);
        // 所选月总天数
        $month_days = date('t', $month_start_time);

        $days = [];
        for($i = 1; $i <= $month_days; $i ++) {
            $for_time = $month_start_time + (($i - 1) * 86400);
            $for_date = date('Y-m-d', $for_time);

            // 如果不是当前月，全是 before, 如果是当前月判断 日期是当前日期的 前面，还是后面
            $current = !$is_current ? ($month > date('Y-m') ? 'after' : 'before') : 
                            ($for_date == $today ? 'today' : 
                                ($for_date < $today ? 'before' : 'after'));

            $days[] = [
                'is_sign' => in_array($for_date, $sign_dates),      // 判断循环的日期，是否在查询的签到记录里面
                'date' => $for_date,
                'time' => $for_time,
                'day' => $i,
                'week' => date('w', $for_time),
                'current' => $current
            ];
        }

        $result = ['days' => $days];

        // 如果是当前月，计算签到时长
        if ($is_current) {
            $continue_days = 0;     // 连续签到天数    
            $chunk = 0;             // 第几次 chunk;
            $chunk_num = 10;        // 每次查 10 条
            $sign = self::where('user_id', $user->id)->chunk($chunk_num, function ($signs) use ($totime, &$continue_days, &$chunk, $chunk_num) {
                foreach ($signs as $key => $sign) {
                    $pre_time = $totime - (86400 * ($key + ($chunk * $chunk_num)));
                    $pre_date = date('Y-m-d', $pre_time);
                    if ($sign->date == $pre_date) {
                        $continue_days++;
                    } else {
                        return false;
                    }
                }
                $chunk ++;
            }, 'date', 'desc');     // 如果 date 重复，有坑 (date < 2020-03-28)

            $result['cuntinue_days'] = $continue_days; 
        }
        
        return $result;
    }


    // 添加签到记录
    public static function sign($params) {
        $user = User::info();

        $sign = Db::transaction(function () use ($user, $params) {
            extract($params);

            $sign = self::where('user_id', $user->id)->where('date', date('Y-m-d'))->lock(true)->find();

            if ($sign) {
                new Exception('您今天已经签到，明天再来吧');
            }

            // 当前时间戳，避免程序执行中间，刚好跨天
            $time = time();

            // 获取积分规则
            $config = Config::where('name', 'score')->find();
            $config = json_decode($config['value'], true);
            $everyday = $config['everyday'];
            $inc_value = $config['inc_value'];
            $until_day = $config['until_day'];

            // 查询签到记录，判断连续签到天数 只需要倒叙查询 $until_day 条记录
            $signs = self::where('user_id', $user->id)->order('date', 'desc')->limit($until_day)->select();

            $continue_days = 1;
            foreach ($signs as $key => $sign) {
                $pre_time = $time - (86400 * ($key + 1));
                $pre_date = date('Y-m-d', $pre_time);
                if ($sign->date == $pre_date) {
                    $continue_days++;
                } else {
                    break;
                }
            }

            // 连续签到天数超出最大连续天数，按照最大连续天数计算
            $continue_effec_days = (($continue_days - 1) > $until_day) ? $until_day : ($continue_days - 1);

            // 计算今天应得积分  连续签到两天，第二天所得积分为 $everyday + ((2 - 1) * $inc_value)
            $score = $everyday;
            $until_add = $continue_effec_days * $inc_value;       // 连续签到累加必须大于 0 ，小于 0 舍弃
            if ($until_add > 0) {    // 避免 until_day 填写小于 等于 0 
                $score += $until_add;
            }

            // 插入签到记录
            $sign = self::create([
                'user_id' => $user->id,
                'date' => date('Y-m-d', $time),
                'score' => $score >= 0 ? $score : 0,
                'is_replenish' => 0
            ]);

            // 赠送积分
            if ($score > 0) {
                User::score($score, $user->id, 'sign', $sign->id, '', [
                    'date' => date('Y-m-d', $time)
                ]);
            }

            return $sign;
        });

        return $sign;
    }

        // 消费返积分
        public static function consume($params) {
            $user = User::info();
    
            $sign = Db::transaction(function () use ($user, $params) {
                extract($params);
    
                $sign = self::where('user_id', $user->id)->where('date', date('Y-m-d'))->lock(true)->find();
    
                if ($sign) {
                    new Exception('您今天已经签到，明天再来吧');
                }
    
                // 当前时间戳，避免程序执行中间，刚好跨天
                $time = time();
    
                // 获取积分规则
                $config = Config::where('name', 'score')->find();
                $config = json_decode($config['value'], true);
                $everyday = $config['everyday'];
                $inc_value = $config['inc_value'];
                $until_day = $config['until_day'];
    
                // 查询签到记录，判断连续签到天数 只需要倒叙查询 $until_day 条记录
                $signs = self::where('user_id', $user->id)->order('date', 'desc')->limit($until_day)->select();
    
                $continue_days = 1;
                foreach ($signs as $key => $sign) {
                    $pre_time = $time - (86400 * ($key + 1));
                    $pre_date = date('Y-m-d', $pre_time);
                    if ($sign->date == $pre_date) {
                        $continue_days++;
                    } else {
                        break;
                    }
                }
    
                // 连续签到天数超出最大连续天数，按照最大连续天数计算
                $continue_effec_days = (($continue_days - 1) > $until_day) ? $until_day : ($continue_days - 1);
    
                // 计算今天应得积分  连续签到两天，第二天所得积分为 $everyday + ((2 - 1) * $inc_value)
                $score = $everyday;
                $until_add = $continue_effec_days * $inc_value;       // 连续签到累加必须大于 0 ，小于 0 舍弃
                if ($until_add > 0) {    // 避免 until_day 填写小于 等于 0 
                    $score += $until_add;
                }
    
                // 插入签到记录
                $sign = self::create([
                    'user_id' => $user->id,
                    'date' => date('Y-m-d', $time),
                    'score' => $score >= 0 ? $score : 0,
                    'is_replenish' => 0
                ]);
    
                // 赠送积分
                if ($score > 0) {
                    User::score($score, $user->id, 'sign', $sign->id, '', [
                        'date' => date('Y-m-d', $time)
                    ]);
                }
    
                return $sign;
            });
    
            return $sign;
        }

}
