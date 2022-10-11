<?php

use think\Db;

class Subscribe{

/**
 * Notes:获取accessToken
 * @return mixed
 * @throws \think\Exception
 * @throws \think\exception\PDOException
 */
public function getAccessToken()
{
    //当前时间戳
    $now_time = strtotime(date('Y-m-d H:i:s',time())) ;

    //失效时间
    $timeout = 7200 ;

    //判断access_token是否过期
    $before_time = $now_time - $timeout ;

    //未查找到就为过期
    $access_token = Db::table('takeout_access_token')->where('id',1)
        ->where('update_time' ,'>',$before_time)
        ->value('access_token');

    //如果过期
    if( !$access_token ) {

        //获取新的access_token
        $appid  = "wxf0a330ff0b988465";
        $secret = "";
        $url    = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$secret;
        $res = json_decode(file_get_contents($url),true);

        $access_token = $res['access_token'] ;

        //更新数据库
        $update = ['access_token' => $access_token ,'update_time' => $now_time] ;
        Db::table('takeout_access_token')->where('id',1)->update($update) ;
    }

    return $access_token ;
}



//发送订阅消息
public function sendSubscribeMessage($touser,$datast)
{
    //access_token
    $access_token = self::getAccessToken() ;

    //请求url
    $url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $access_token ;

    //发送内容
    $data = [] ;

    //接收者（用户）的 openid
    $data['touser'] = $touser ;

    //所需下发的订阅模板id
    $data['template_id'] = "sYSKqLHeAXpQF2c9kZFa5JTHIMUeN0jNnmWdrcwfbGU" ;

   
    //模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
    $data['data'] = [
        "thing1"=>[
            'value' => '123456'
        ],
        "thing2"=>[
            'value' => '123456'
        ],
        "thing3"=>[
            'value' => '123'
        ],
        'thing4'=>[
            'value'=>'123456'
        ]
    ];

    

    return self::curlPost($url,json_encode($data)) ;
}
//发送post请求
protected function curlPost($url,$data)
{
    $ch = curl_init();
    $params[CURLOPT_URL] = $url;    //请求url地址
    $params[CURLOPT_HEADER] = FALSE; //是否返回响应头信息
    $params[CURLOPT_SSL_VERIFYPEER] = false;
    $params[CURLOPT_SSL_VERIFYHOST] = false;
    $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
    $params[CURLOPT_POST] = true;
    $params[CURLOPT_POSTFIELDS] = $data;
    curl_setopt_array($ch, $params); //传入curl参数
    $content = curl_exec($ch); //执行
    curl_close($ch); //关闭连接
    return $content;
}






}


?>