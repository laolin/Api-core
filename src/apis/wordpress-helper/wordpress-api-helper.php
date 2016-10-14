<?php

class WAPI{

  const TOKEN = 'wp_token';
  const SIGNATURE = 'wp_signature';
  const TIMESTAMP = 'timestamp';
  const UID = 'uid';
  
  const TOKEN_SALT= 'I*f)Os*&iopna)78';
  const WX_KEY = 'weixin_unionid';
  
  private $_wp_path;
  private $_app_id;
  private $_app_sec;
  
  function __construct() {
    $wp_path=api_g("path-wp");
    $wp_load=$wp_path .'/wp-load.php';
    if( ! file_exists($wp_load )) {
      die ( "Error wp-load ($wp_load)" );
    }
    require( $wp_load  );
    $this->_wp_path=$wp_path;
  }
  
  /* ================================================
   apis of user:
     user_login
     user_checktok
  */
  public function user_login() {// login
    $msg=API::msg(0,'login ok.');
    
    $user=API::INP('user');
    $pass=API::INP('pass');
    $re=wp_authenticate($user,$pass);
    //$msg['login']=$re;
    if(is_wp_error($re))
      return API::msg(1101,'Error login.',$msg);
    $uid=$re->ID;
    
    $tok=self::__token_gen($uid);
    $msg[self::TOKEN]=$tok;
    update_usermeta($uid,self::TOKEN,$tok);
    return $msg;
  }
  public function user_auth() {
    return self::__verify_signature();
  }
  
  /* ===================================
   apis of post:
   
  */
  
  public function post_add() {// Create post object
    $msg=self::__token_verify();
    if(is_error($msg))
      return $msg;
    
    $my_post = array(
      'post_title'    => wp_strip_all_tags(  $_POST['post_title'] ),
      'post_content'  => $_POST['post_content'],
      'post_status'   => 'publish',
      'post_author'   => 1,
      'post_category' => array( 1,2)
    );
     
    // Insert the post into the database
    $a=API::msg(0,'Post_add done at: ' . time());
    $a['s2']=wp_insert_post( $my_post );
    return $a;
  }
  function post_get($p) {
    get_post($p);
  }
  
  /* ================================================
    Helper functions
    
  */
  //生成一个随机字符串
  static private function __token_gen($uid){
    list($usec, $sec) = explode(' ', microtime());
    srand($sec + $usec * 1000000);
    $str=substr( sha1( self::TOKEN_SALT . rand() . uniqid() ) ,-5);//先取短点，若有需要可以取长点
    $day=0+date('ymd',time()+ 8*24*3600);//约7天多有效
    
    return "U-$uid-$day-$str";
  }

 
  // 验证
  // SIGNATURE 应该 == md5($tok.$tim.$api);
  static private function __verify_signature(){
    $sig=API::INP(self::SIGNATURE);
    $uid=API::INP(self::UID);
    $tim=API::INP(self::TIMESTAMP);
    
    $msg=API::msg(0,'Signature is ok.');

        
    $timestamp=time();//注，初始化页面里已有强制指定东8区的代码，能确保是东8区的 timestamp
    if( abs(0 + $tim - $timestamp ) > 10*60 )return API::msg(104,"TIMESTAMP($tim) offset error (server=$timestamp).",$msg);
    if($uid<=0) return API::msg(101,'UID error.');
    if(empty($sig)) return API::msg(102,'SIGNATURE error.');
    
    
    $tok = get_user_meta($uid,self::TOKEN,true);    
    if(empty($tok)) return API::msg(201,'UID is bad.');
     
    $ar=explode('-',$tok);
    $m=['ar'=>$ar,'u'=>$uid,'p'=>$pub];
   
    if( $ar[1]  != $uid) return API::msg(201,'UID is bad(2).',$m);
    
    
    //先不检查过期 
    //if(0+date('ymd') > 0+$ar[2] )//检验截止有效日期
    //  return API::msg(211,'Token expired, please re-login to get new token.');

    
    //$api=api_g('api');
    $u=parse_url($_SERVER['REQUEST_URI']);
    $api=$u['path'];
    $msg['api8']=$api;
    $msg[self::UID]=$uid;
    //$msg['sv']=$_SERVER;
    $rit=md5($tok.$tim.$api);
    if($sig == $rit) return $msg;
    if(SHOW_DEBUG_INFO) {
      $msg['src']=($tok.$tim.$api);
      $msg['rit2']=$rit;
    }
    return API::msg(100,"Signature failure.",$msg);
  }
}
  


