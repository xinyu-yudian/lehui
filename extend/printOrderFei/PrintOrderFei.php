<?php
namespace PrintOrderFei;

/**
     * @auther hotlinhao
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
*/

use Exception;
use think\Db;

define('USER', '13703005653@139.com');  //*必填*：飞鹅云后台注册账号
define('UKEY', 'ZZeg7WH2u49FS4nH');  //*必填*: 飞鹅云后台注册账号后生成的UKEY 【备注：这不是填打印机的KEY】
define('SN', '922599200');      //*必填*：打印机编号，必须要在管理后台里添加打印机或调用API接口添加之后，才能调用API

//以下参数不需要修改
define('IP','api.feieyun.cn');      //接口IP或域名
define('PORT',80);            //接口IP端口
define('PATH','/Api/Open/');    //接口路径

class PrintOrderFei
{
    public function printOrder($order_sn){
        //获取打印信息
        $content = $this->PrintDingDan($order_sn);
        $time = time();         //请求时间
        $msgInfo = array(
          'user'=>USER,
          'stime'=>$time,
          'sig'=>$this->signature($time),
          'apiname'=>'Open_printMsg',
          'sn'=>SN,
          'content'=>$content,
          'times'=>1//打印次数
        );

        $client = new HttpClient(IP,PORT);
        if(!$client->post(PATH,$msgInfo)){
          $res = ['code'=>0,'msg'=>'失败'];
        }else{
          //服务器返回的JSON字符串，建议要当做日志记录起来 
          $result = $client->getContent();
          $result = json_decode($result, true);

            //将打印信息存入数据库的打印表单(fa_print_order)
            $nowtime = intval(time());
            $results = json_encode($result,JSON_FORCE_OBJECT);
            $order_sns = intval($order_sn);
            $data = ['print_order_sn' => $order_sns, 
                     'print_order_content' => $results, 
                     'print_order_createtime' => $nowtime];
            try{            
                Db::table('fa_print_order')->insert($data);
            }catch(Exception $e){
                echo "打印数据录入数据库失败:".$e;
            } 
            
            
        //   echo $result;
            if($result['ret'] == 0){
                $res = ['code'=>1,'msg'=>'成功'];
            } else {
                $res = ['code'=>0,'msg'=>$result['msg']];
            }
          
        }
        return $res;
    }

    
    // 根据订单ID组装小票内容
    private function PrintDingDan($order_sn){
                
        $orders = Db::table('fa_shopro_order')->where('order_sn',$order_sn)->find();
        $orders_irems = Db::table('fa_shopro_order_item')->where('order_id',$orders['id'])->select();
        

        $content = '<CB>乐烩C厨</CB><BR>';
        $content .= '名称　     单价  优惠 数量 金额<BR>';
        $content .= '--------------------------------<BR>';

        $zm = 0;
        foreach($orders_irems as $orders_irem)
        {
        $content .= $orders_irem['goods_title'].'  '.$orders_irem['goods_original_price'].'  '.$orders_irem['discount_fee'].'  '.$orders_irem['goods_num'].'  '.$orders_irem['goods_price'].'<BR>';
        $zm += $orders_irem['goods_price'];
        }

        $content .= '--------------------------------<BR>';
        $content .= '              优惠   <BR>';
        $content .= '折扣优惠：'.$orders['discount_fee'].'元<BR>';
        $content .= '优惠券：'.$orders['coupon_fee'].'元<BR>';
        $content .= '活动优惠：'.$orders['activity_discount_money'].'元<BR>';
        $content .= '合计优惠：'.number_format(($orders['discount_fee']+$orders['coupon_fee']+$orders['activity_discount_money']),2).'元<BR>';

        $content .= '--------------------------------<BR>';
        $content .= '其他服务费用     优惠   金额<BR>';
        $content .= '配送费：'.$orders['dispatch_amount'].'     '.$orders['dispatch_discount_money'].'    '.number_format(($orders['dispatch_amount']-$orders['dispatch_discount_money']),2).'<BR>';
        $cook_money = 0;
        if($orders['is_cook'] == 1){
            $content .= '厨师上门：'.$orders['cook_service_amount'].'  '.$orders['cook_service_discount_money'].'   '.number_format(($orders['cook_service_amount']-$orders['cook_service_discount_money']),2).'<BR>';
            $cook_money = number_format($orders['cook_service_amount']-$orders['cook_service_discount_money'],2);
        }
        
        $content .= '--------------------------------<BR>';
        $content .= '订单号：'.$order_sn.'<BR>';
        $content .= '姓名：<B>'.$orders['consignee'].'</B><BR><BR>';
        $content .= '备注：<B>'.$orders["remark"].'</B><BR><BR>';
        $content .= '订单总金额：'.$orders['total_amount'].'元<BR>';
        $content .= '合计支付：<B>'.$orders['total_fee'].'</B>元<BR>';       
        if($orders["score_fee"] != 0)
        {
            $content .= '积分支付：'.$orders["score_fee"].'积分<BR>';
        }
        $content .= '支付方式：';
        if($orders['pay_type'] == 'wechat'){
            $content .='微信支付<BR>';
        }else if($orders['pay_type'] == 'alipay'){
            $content .='支付宝<BR>';
        }else if($orders['pay_type'] == 'wallet'){
            $content .='钱包支付<BR>';
        }else { $content .='其他支付<BR>'; }
        $content .= '送货地点：'.$orders["address"].'<BR>';
        $content .= '联系电话：'.$orders["phone"].'<BR>';
        $content .= '订餐时间：'.date("Y-m-d H:i:s",$orders["createtime"]).'<BR><BR>';

        $content .= '<QR>https://mp.weixin.qq.com/a/~LX56UlBpRP2KfXbySFvg7Q~~</QR>';//把二维码字符串用标签套上即可自动生成二维码

        
        return $content;
    }

    /**
     * [signature 生成签名]
     * @param  [string] $time [当前UNIX时间戳，10位，精确到秒]
     * @return [string]       [接口返回值]
     */
    public function signature($time){
        return sha1(USER.UKEY.$time);//公共参数，请求公钥
    }
}

class HttpClient {
    // Request vars
    var $host;
    var $port;
    var $path;
    var $method;
    var $postdata = '';
    var $cookies = array();
    var $referer;
    var $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    var $accept_encoding = 'gzip';
    var $accept_language = 'en-us';
    var $user_agent = 'Incutio HttpClient v0.9';
    var $timeout = 20;
    var $use_gzip = true;
    var $persist_cookies = true; 
    var $persist_referers = true; 
    var $debug = false;
    var $handle_redirects = true; 
    var $max_redirects = 5;
    var $headers_only = false;    
    var $username;
    var $password;
    var $status;
    var $headers = array();
    var $content = '';
    var $errormsg;
    var $redirect_count = 0;
    var $cookie_host = '';
    function __construct($host, $port=80) {
        $this->host = $host;
        $this->port = $port;
    }
    function get($path, $data = false) {
        $this->path = $path;
        $this->method = 'GET';
        if ($data) {
            $this->path .= '?'.$this->buildQueryString($data);
        }
        return $this->doRequest();
    }
    function post($path, $data) {
        $this->path = $path;
        $this->method = 'POST';
        $this->postdata = $this->buildQueryString($data);
        return $this->doRequest();
    }
    function buildQueryString($data) {
        $querystring = '';
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $val2) {
                        $querystring .= urlencode($key).'='.urlencode($val2).'&';
                    }
                } else {
                    $querystring .= urlencode($key).'='.urlencode($val).'&';
                }
            }
            $querystring = substr($querystring, 0, -1); // Eliminate unnecessary &
        } else {
            $querystring = $data;
        }
        return $querystring;
    }
    function doRequest() {
        if (!$fp = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout)) {
            switch($errno) {
                case -3:
                    $this->errormsg = 'Socket creation failed (-3)';
                case -4:
                    $this->errormsg = 'DNS lookup failure (-4)';
                case -5:
                    $this->errormsg = 'Connection refused or timed out (-5)';
                default:
                    $this->errormsg = 'Connection failed ('.$errno.')';
                $this->errormsg .= ' '.$errstr;
                $this->debug($this->errormsg);
            }
            return false;
        }
        socket_set_timeout($fp, $this->timeout);
        $request = $this->buildRequest();
        $this->debug('Request', $request);
        fwrite($fp, $request);
        $this->headers = array();
        $this->content = '';
        $this->errormsg = '';
        $inHeaders = true;
        $atStart = true;
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($atStart) {
                $atStart = false;
                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
                    $this->errormsg = "Status code line invalid: ".htmlentities($line);
                    $this->debug($this->errormsg);
                    return false;
                }
                $http_version = $m[1]; 
                $this->status = $m[2];
                $status_string = $m[3];
                $this->debug(trim($line));
                continue;
            }
            if ($inHeaders) {
                if (trim($line) == '') {
                    $inHeaders = false;
                    $this->debug('Received Headers', $this->headers);
                    if ($this->headers_only) {
                        break;
                    }
                    continue;
                }
                if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
                    continue;
                }
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                if (isset($this->headers[$key])) {
                    if (is_array($this->headers[$key])) {
                        $this->headers[$key][] = $val;
                    } else {
                        $this->headers[$key] = array($this->headers[$key], $val);
                    }
                } else {
                    $this->headers[$key] = $val;
                }
                continue;
            }
            $this->content .= $line;
        }
        fclose($fp);
        if (isset($this->headers['content-encoding']) && $this->headers['content-encoding'] == 'gzip') {
            $this->debug('Content is gzip encoded, unzipping it');
            $this->content = substr($this->content, 10);
            $this->content = gzinflate($this->content);
        }
        if ($this->persist_cookies && isset($this->headers['set-cookie']) && $this->host == $this->cookie_host) {
            $cookies = $this->headers['set-cookie'];
            if (!is_array($cookies)) {
                $cookies = array($cookies);
            }
            foreach ($cookies as $cookie) {
                if (preg_match('/([^=]+)=([^;]+);/', $cookie, $m)) {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
            $this->cookie_host = $this->host;
        }
        if ($this->persist_referers) {
            $this->debug('Persisting referer: '.$this->getRequestURL());
            $this->referer = $this->getRequestURL();
        }
        if ($this->handle_redirects) {
            if (++$this->redirect_count >= $this->max_redirects) {
                $this->errormsg = 'Number of redirects exceeded maximum ('.$this->max_redirects.')';
                $this->debug($this->errormsg);
                $this->redirect_count = 0;
                return false;
            }
            $location = isset($this->headers['location']) ? $this->headers['location'] : '';
            $uri = isset($this->headers['uri']) ? $this->headers['uri'] : '';
            if ($location || $uri) {
                $url = parse_url($location.$uri);
                return $this->get($url['path']);
            }
        }
        return true;
    }
    function buildRequest() {
        $headers = array();
        $headers[] = "{$this->method} {$this->path} HTTP/1.0"; 
        $headers[] = "Host: {$this->host}";
        $headers[] = "User-Agent: {$this->user_agent}";
        $headers[] = "Accept: {$this->accept}";
        if ($this->use_gzip) {
            $headers[] = "Accept-encoding: {$this->accept_encoding}";
        }
        $headers[] = "Accept-language: {$this->accept_language}";
        if ($this->referer) {
            $headers[] = "Referer: {$this->referer}";
        }
        if ($this->cookies) {
            $cookie = 'Cookie: ';
            foreach ($this->cookies as $key => $value) {
                $cookie .= "$key=$value; ";
            }
            $headers[] = $cookie;
        }
        if ($this->username && $this->password) {
            $headers[] = 'Authorization: BASIC '.base64_encode($this->username.':'.$this->password);
        }
        if ($this->postdata) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: '.strlen($this->postdata);
        }
        $request = implode("\r\n", $headers)."\r\n\r\n".$this->postdata;
        return $request;
    }
    function getStatus() {
        return $this->status;
    }
    function getContent() {
        return $this->content;
    }
    function getHeaders() {
        return $this->headers;
    }
    function getHeader($header) {
        $header = strtolower($header);
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        } else {
            return false;
        }
    }
    function getError() {
        return $this->errormsg;
    }
    function getCookies() {
        return $this->cookies;
    }
    function getRequestURL() {
        $url = 'https://'.$this->host;
        if ($this->port != 80) {
            $url .= ':'.$this->port;
        }            
        $url .= $this->path;
        return $url;
    }
    function setUserAgent($string) {
        $this->user_agent = $string;
    }
    function setAuthorization($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
    function setCookies($array) {
        $this->cookies = $array;
    }
    function useGzip($boolean) {
        $this->use_gzip = $boolean;
    }
    function setPersistCookies($boolean) {
        $this->persist_cookies = $boolean;
    }
    function setPersistReferers($boolean) {
        $this->persist_referers = $boolean;
    }
    function setHandleRedirects($boolean) {
        $this->handle_redirects = $boolean;
    }
    function setMaxRedirects($num) {
        $this->max_redirects = $num;
    }
    function setHeadersOnly($boolean) {
        $this->headers_only = $boolean;
    }
    function setDebug($boolean) {
        $this->debug = $boolean;
    }
    function quickGet($url) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        if (isset($bits['query'])) {
            $path .= '?'.$bits['query'];
        }
        $client = new HttpClient($host, $port);
        if (!$client->get($path)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
    function quickPost($url, $data) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        $client = new HttpClient($host, $port);
        if (!$client->post($path, $data)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
    function debug($msg, $object = false) {
        if ($this->debug) {
            print '<div style="border: 1px solid red; padding: 0.5em; margin: 0.5em;"><strong>HttpClient Debug:</strong> '.$msg;
            if ($object) {
                ob_start();
                print_r($object);
                $content = htmlentities(ob_get_contents());
                ob_end_clean();
                print '<pre>'.$content.'</pre>';
            }
            print '</div>';
        }
    }   
}