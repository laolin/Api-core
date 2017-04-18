<?php
// ================================
/*
*/

class class_user{
  public static function main( $para1,$para2) {
    $res=API::data(['time'=>time()]);
    return $res;
  }
  
  //test
  public static function test( ) {
    
    $r=USER::userVerify();
    return API::data($r);
  }
  
  //返回用于加密密码的 salt str
  public static function salt( $para1,$para2) {
    return API::data(api_g("usr-salt"));
  }
  
  //-1: 非法
  //字符串: 已存在用户的密码
  //0: 可以注册
  public static function exist( $para1,$para2) {
    $uname=API::INP('uname');
    return USER::exist( $uname );
  }
  
  //需要 防机器人注册
  /*
  reg(注册)api说明
  
  输入参数：
    uname 有效用户名
    upass 用规定版本号对应算法加密过的密码
    
  返回：
    注册成功后，返回用户的 uid
  */
  public static function reg( $para1,$para2) {
    
    $uname=API::INP('uname');
    $upass=API::INP('upass');
    
    return USER::reg( $uname,$upass );
  }

  //需要 防机器人 登录
  //正式使用时不需要 对错误的返回值进行区分，不利于安全 
  /*
  login(登录) api说明
  
  输入参数：
    uid   有效的用户 uid
    uname 有效用户名 （当无 uid 时，才使用 uname ）
    upass 用规定版本号对应算法加密过的密码
    //tokenid 目前都是1，即每用户只有一个token，以后允许多tok
    
  返回：
    注册成功后，返回用户的 uid, uname, token
  */

  public static function login( ) {
  
    $uid=API::INP('uid');//先 根据 id 登录
    $uname=API::INP('uname'); //没有 id 时，再使用 username
    $upass=API::INP('upass');
    
    return USER::login( $uid,$uname,$upass );
  }

    

}
