<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
 
*/
class class_wxjsapi{
    public static function main( $para1,$para2) {
      return self::sign();
    }

    /* //不提供此功能
    public static function token() {
      $res=API::msg(0,'Ok.');
      $db=API::db();
      $jssdk = new JSSDK( api_g("WX_APPID"), api_g("WX_APPSEC"),$db );
      $a=$jssdk->getToken();
      $res['token']=$a;
      return $res;
    }*/
     public static function sign() {
      $res=API::msg(0,'Ok.');
      $db=API::db();
      $jssdk = new JSSDK( api_g("WX_APPID"), api_g("WX_APPSEC"),$db );
      $a=$jssdk->getSign();
      $res['sign']=$a;
      $res['ver']=1.2;
      return $res;
    }
   
    
}//== END OF class =========================================================

//=========================================================
    
/*
注意，这个API需要：1，数据库；2，WXAPP资料。
故需要在index.config.php中正确定义数据库，定义APPID和APPSEC。
然后按下表创建数据库
```
CREATE TABLE IF NOT EXISTS `wx_cache_01` (
  `id` int(11) NOT NULL,
  `appid` varchar(26) NOT NULL,
  `appsecrect` varchar(42) DEFAULT NULL,
  `appdisc` varchar(64) NOT NULL,
  `apptype` varchar(32) DEFAULT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  `jsapi_ticket` varchar(255) DEFAULT NULL,
  `expire_time` int(11) DEFAULT NULL,
  `ext` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `wx_cache_01`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appid` (`appid`);

INSERT INTO `wx_cache_01` (`id`, `appid`, `appsecrect`, `appdisc`, `apptype`, `access_token`, `jsapi_ticket`, `expire_time`, `ext`) VALUES
(1, 'wx3dd28920a07c53de', '', '老林', '个人', '', '', 1481214466, 0);

```

*/
class JSSDK {
  private $appId;
  private $appSecret;
  private $db;

  public function __construct($appId, $appSecret,$db) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
    $this->db=$db;
  }
  public function getToken() {
    //id appid appsecrect appdisc apptype access_token jsapi_ticket expire_time
    $r=$this->db->select('wx_cache_01',
        ['id','access_token','jsapi_ticket','expire_time'],
        ["or" => [
                    'appid'=>$this->appId
                 ] ,
          "LIMIT" => 1  ]);
    $data= count($r)==1 ? $r[0] :[] ;
    if($data['expire_time'] < time()-600 ) {//留10分钟误差
      $data = $this->_regetToken();
      $this->db->update('wx_cache_01',
        $data,
        ["or" => [
                    'appid'=>$this->appId
                 ] ,
          "LIMIT" => 1  ]);
    }
    
    return $data;
  }
  
  
  private function _regetToken() {
    // 如果是企业号用以下URL获取access_token
    // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
    $res = json_decode($this->httpGet($url));
    $accessToken = $res->access_token;
    
    // 如果是企业号用以下 URL 获取 ticket
    // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
    $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
    $res = json_decode($this->httpGet($url));
    $ticket = $res->ticket;
      
    return ['access_token'=>$accessToken,'jsapi_ticket'=>$ticket,'expire_time'=>time()+7200];
  }
  
  public function getSign() {
    $t=$this->getToken();
    $jsapiTicket=$t['jsapi_ticket'];
    $url=API::GET('url');
    $timestamp = time();
    $nonceStr = $this->createNonceStr();
    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $signPackage; 
  }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    
    //这里来自微信提供的参考样例，但是这里php运行会出现有一个notice
    // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
    // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }
}


    
    
    
    
