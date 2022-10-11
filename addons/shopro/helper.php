<?php

if (!function_exists('matchLatLng')) {
    function matchLatLng($latlng) {
        $match = "/^\d{1,3}\.\d{1,30}$/";
        return preg_match($match, $latlng) ? $latlng : 0;
    }
}


if (!function_exists('getDistanceBuilder')) {
    function getDistanceBuilder($lat, $lng) {
        return "ROUND(6378.138 * 2 * ASIN(SQRT(POW(SIN((". matchLatLng($lat) . " * PI() / 180 - latitude * PI() / 180) / 2), 2) + COS(". matchLatLng($lat). " * PI() / 180) * COS(latitude * PI() / 180) * POW(SIN((". matchLatLng($lng). " * PI() / 180 - longitude * PI() / 180) / 2), 2))) * 1000) AS distance";
    }
}


/**
 * 下划线转驼峰
 * step1.原字符串转小写,原字符串中的分隔符用空格替换,在字符串开头加上分隔符
 * step2.将字符串中每个单词的首字母转换为大写,再去空格,去字符串首部附加的分隔符.
 */
if (!function_exists('camelize')) {
    function camelize($uncamelized_words, $separator = '_') {
        $uncamelized_words = $separator . str_replace($separator, " ", strtolower($uncamelized_words));
        return ltrim(str_replace(" ", "", ucwords($uncamelized_words)), $separator);
    }
}

/**
 * 驼峰命名转下划线命名
 * 思路:
 * 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
 */
if (!function_exists('uncamelize')) {
    function uncamelize($camelCaps, $separator='_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }
}


/**
 * 检测系统必要环境
 */
if (!function_exists('checkEnv')) {
    function checkEnv($need = [], $is_throw = true)
    {
        $need = is_string($need) ? [$need] : $need;

        // 检测是否安装浮点数运算扩展
        if (in_array('bcmath', $need)) {
            if (!extension_loaded('bcmath')) {
                if ($is_throw) {
                    new \addons\shopro\exception\Exception('请安装浮点数扩展 【bcmath】');
                } else {
                    return false;
                }
            }
        }

        // 检测是否安装了队列
        if (in_array('queue', $need)) {
            if (!class_exists(\think\Queue::class)) {
                if ($is_throw) {
                    new \addons\shopro\exception\Exception('请安装 【topthink/think-queue:v1.1.6 队列扩展】');
                } else {
                    return false;
                }
            }
        }

        if (in_array('commission', $need)) {
            if (!class_exists(\addons\shopro\listener\commission\CommissionHook::class)) {
                if ($is_throw) {
                    new \addons\shopro\exception\Exception('请先升级 【shopro】');
                } else {
                    return false;
                }
            }
        }

        if (in_array('yansongda', $need)) {
            if (!class_exists(\Yansongda\Pay\Pay::class)) {
                if ($is_throw) {
                    new \addons\shopro\exception\Exception('请在后台安装 【微信支付宝整合插件】');
                } else {
                    return false;
                }
            }
        }
        return true;
    }
}


/**
 * 删除 sql mode 指定模式，或者直接关闭 sql mode
 */
if (!function_exists('closeStrict')) {
    function closeStrict($modes = [])
    {
        $modes = array_filter(is_array($modes) ? $modes : [$modes]);

        $result = \think\Db::query("SELECT @@session.sql_mode");
        $newModes = $oldModes = explode(',', ($result[0]['@@session.sql_mode'] ?? ''));

        if ($modes) {
            foreach ($modes as $mode) {
                $delkey = array_search($mode, $newModes);
                if ($delkey !== false) {
                    unset($newModes[$delkey]);
                }
            }
            $newModes = join(',', array_values(array_filter($newModes)));
        } else {
            $newModes = '';
        }

        \think\Db::execute("set session sql_mode='" . $newModes . "'");

        return $oldModes;
    }
}


/**
 * 重新打开被关闭的 sql mode
 */
if (!function_exists('recoverStrict')) {
    function recoverStrict($modes = [], $append = false)
    {
        if ($append) {
            $result = \think\Db::query("SELECT @@session.sql_mode");
            $oldModes = explode(',', ($result[0]['@@session.sql_mode'] ?? ''));

            $modes = array_values(array_filter(array_unique(array_merge($oldModes, $modes))));
        }

        \think\Db::execute("set session sql_mode='" . join(',', $modes) . "'");
    }
}
/**
 * 判断坐标是否在多边形内
 */
if (!function_exists('inArea')) {
    // 判断坐标是否在多边形内
    function inArea($x,$y,$arr)
    {
        //点的数量
        $count = count($arr);
        $n = 0; //点与线相交的个数
        $bool = 0;//外
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i, $i++) {
            //两个点一条线 取出两个连接点的定点
            $px1 = $arr[$i][0];
            $py1 = $arr[$i][1];
            $px2 = $arr[$j][0];
            $py2 = $arr[$j][1];
            //$x的水平位置画射线
            if($x>=$px1 || $x>= $px2)
            {
                //判断$y 是否在线的区域
                if(($y>=$py1 && $y<=$py2) || ($y>=$py2 && $y<= $py1)){


                    if (($y == $py1 && $x == $px1) || ($y == $py2 && $x == $px2)) {

                        #如果$x的值和点的坐标相同
                        $bool = 2;//在点上
                        return $bool;

                    }else{
                        $px = $px1+($y-$py1)/($py2-$py1)*($px2-$px1) ;
                        if($px ==$x)
                        {
                            $bool = 3;//在线上
                        }elseif($px< $x){
                            $n++;
                        }

                    }
                }
            }

        }
        if ($n%2 != 0) {
            $bool = 1;
        }
        return $bool;
    }
}
