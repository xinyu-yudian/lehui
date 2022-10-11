<?php
namespace xcxCode;

class XcxCode
{
    private $_appid;
    private $_srcret;
    /**
     * 构造函数
     */
    public function __construct($appid, $srcret) {
        $this->_appid = $appid;
        $this->_srcret = $srcret;
    }

//    获取accesstoken
    public function getAccesstoken(){
        $tokenUrl="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->_appid."&secret=".$this->_srcret;
        $getArr=array();
        $tokenArr=json_decode($this->send_post($tokenUrl,$getArr,"GET"));
        $access_token=$tokenArr->access_token;
        return $access_token;
    }
    public function send_post($url, $post_data,$method='POST') {
        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => $method, //or GET
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60 // 超时时间（单位:s）
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }
    public function api_notice_increment($url, $data){
        $ch = curl_init();
        $header=array('Accept-Language:zh-CN','x-appkey:114816004000028','x-apsignature:933931F9124593865313864503D477035C0F6A0C551804320036A2A1C5DF38297C9A4D30BB1714EC53214BD92112FB31B4A6FAB466EEF245710CC83D840D410A7592D262B09D0A5D0FE3A2295A81F32D4C75EBD65FA846004A42248B096EDE2FEE84EDEBEBEC321C237D99483AB51235FCB900AD501C07A9CAD2F415C36DED82','x-apversion:1.0','Content-Type:application/x-www-form-urlencoded','Accept-Charset: utf-8','Accept:application/json','X-APFormat:json');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        //         var_dump($tmpInfo);
        //        exit;
        if (curl_errno($ch)) {
            return false;
        }else{
            // var_dump($tmpInfo);
            return $tmpInfo;
        }
    }
    /*上面生成的是数量限制10万的二维码，下面重写数量不限制的码*/
    /*getWXACodeUnlimit*/
    /*码一，圆形的小程序二维码，数量限制一分钟五千条*/
    /*45009    调用分钟频率受限(目前5000次/分钟，会调整)，如需大量小程序码，建议预生成。
    41030    所传page页面不存在，或者小程序没有发布*/
    public function mpcode($page,$cardid,$width=430){
        //参数
//        $postdata['scene']="nidaodaodao";
        $postdata['scene']=$cardid;
        // 宽度
        $postdata['width']=$width;
        // 页面
        $postdata['page']=$page;
//        $postdata['page']="pages/postcard/postcard";
        // 线条颜色
        $postdata['auto_color']=false;
        //auto_color 为 false 时生效
        $postdata['line_color']=['r'=>'0','g'=>'0','b'=>'0'];
        // 是否有底色为true时是透明的
        $postdata['is_hyaline']=false;
        $post_data = json_encode($postdata);
        $access_token=$this->getAccesstoken();
        $url="https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".$access_token;
        $result=$this->api_notice_increment($url,$post_data);
        return $result;
        $data='image/png;base64,'.base64_encode($result);
        return $data;
//        echo '<img src="data:'.$data.'">';
    }
    /*码二，正方形的二维码，数量限制调用十万条*/
    public function qrcodes($path){
        // 宽度
        $postdata['width']=430;
        // 页面
        $postdata['path']=$path;
        $post_data = json_encode($postdata);
        $access_token=$this->getAccesstoken();
        $url="https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token=".$access_token;
        $result=$this->api_notice_increment($url,$post_data);
        return $result;
        $data='image/png;base64,'.base64_encode($result);
        echo '<img src="data:'.$data.'">';
    }
}
