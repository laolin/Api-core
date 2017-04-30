<?php
// ================================
/**
 *  USER::exist( $uname ); 判断用户是否存在
 *  USER::get_users( $arrIds ); 获取多个用户的信息
 *  
 *  USER::userVerify( ); 用在API运行前验证确认用户身份
 *  
 *  USER::reg( $uname,$upass );
 *  USER::login( $uid,$uname,$upass )
 *  USER::changUpass( $uid,$newupass );
 *  
 *  USER::__ADMIN_addUser($uname,$upass);
 *  USER::__ADMIN_addToken($uid,$tokenid);
 *  USER::__ADMIN_changUpass( $uid,$newupass );
 */

class USER {
  const RIGHTS_SUPER_ADMIN = 0x7FFFFFFF; //31 bit = 21 4748 3648
  
  //-1: 非法
  //字符串: 已存在用户的密码
  //0: 可以注册
  public static function exist( $uname ) {
    
    $code=0;
    $info='User not Exist.';
    $test1=self::_getPassByName($uname);
    if($test1 == -1) {
      $code=-1;
      $info='Invalid uname.';
    } else if($test1) {
      $info='User Exist.';
      $code=1;
    }
    return API::data(['code'=>$code,'info'=>$info]);
  }
  
  /**
   *  get_users
   *  获取多个用户的信息
   *  参数 $arrIds  为 id
   */
  public static function get_users( $arrIds ) {
    if(!self::userVerify()) {
      return API::msg(2001,'Error verify token.');
    }
    if(!count($arrIds) || ! is_array($arrIds)) {
      return API::msg(2002,'No id error.');
    }
    $db=API::db();
    
    $prefix=api_g("api-table-prefix");
    $rr=$db->select($prefix.'user',['uid','uname'],
      ['uid'=>$arrIds ]  );

    if(!count($rr)) {
      return API::msg(2003,'No id exist');
    }
    return API::data($rr);

  }
  //需要 防机器人注册
  /*
  reg(注册)api说明
  
  输入参数：
    ver   版本号，目前规定为 'ab'，用于表示密码加密算法
    uname 有效用户名
    upass 用规定版本号对应算法加密过的密码
    
  返回：
    注册成功后，返回用户的 uid
  */
  public static function reg( $uname,$upass ) {
    
    $salt_srv=api_g("usr-salt");
    
    $err='';
    /**
     *  注意，密码格式 长度 35，
     *  内容：
     *  function upass(passwd) {
     *    //salt 见 /user/salt api返回的data值。
     *    var salt = { version : "ab", salt : "api_salt-laolin@&*" }
     *    return salt.version + ':' + hex_md5(passwd + salt.salt );
     *  }
     */
    if(strlen($upass) != 35 ) {
      $err.='Error upass format. Pls use the upass(passwd) function.';
    }
    else if(substr($upass,0,2) != $salt_srv['version']) {
      $err.='RegVerStr Error. Pls use the upass(passwd) function.';
    }
    if( ! $err ) {
      $test1=self::_getPassByName($uname);
      if($test1 == -1) {
        $err.='Invalid uname. ';
      } else if($test1) {
        $err.='User Exist. ';
      }
    }
    if(strlen($err)) {
      return API::msg(600,$err);
    }
    return self::__ADMIN_addUser($uname,$upass);
  }
  public static function __ADMIN_addUser( $uname,$upass='ab:xx' ) {
    $db=API::db();
    $prefix=api_g("api-table-prefix");
    $def_rights=intval(api_g("user_default_rights"));
    if($def_rights<0)$def_rights=0;

    $r=$db->insert($prefix.'user',
      ['uname'=>$uname, 'upass'=>$upass, 'rights'=>$def_rights] );
    return API::data($r);
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

  public static function login( $uid,$uname,$upass ) {
  
    //先 根据 id 登录
    //没有 id 时，再使用 uname
    if(strlen($upass) != 35) {
      return API::msg(702,'Error passwd format. ');
    }
    if ($uid) {
      $p_right=self::_getPassById($uid);
    } else {
      $p_right=self::_getPassByName($uname);//会一起返回id
    }
    if($p_right == -1) {
      return API::msg(703,'Invalid uname/uid.');
    } else if(! $p_right) {
      return API::msg(704,'User Not Exist.');
    }
     
    if($upass !=  $p_right['upass']) { //数据库密码前3个字符用于表示版本号
      return API::msg(705,'Passwd mismatch.');
    }
    if (! $uid) {
      $uid=$p_right['uid'];
    }

    $tokenid=1;//目前都是1，即每用户只有一个token，以后允许多tok
    $newToken = self::__ADMIN_addToken($uid,$tokenid);
    $newToken['uname']=$p_right['uname'];
    return API::data($newToken);
  }
  public static function __ADMIN_addToken($uid,$tokenid) {
    $tok=self::_tokGen($uid,$tokenid);
    self::_tokSave($uid,$tokenid,$tok);
    return ['uid'=>$uid,'token'=>$tok, 'tokenid'=>$tokenid];
  }
  
  public static function changeUpass( $uid,$newupass ) {
    if( !self::userVerify( )) {
      return API::msg(2001,'Error verify token.');
    }
    if(strlen($newupass) != 35) {
      return API::msg(702,'Error passwd format. ');
    }
    return self::__ADMIN_changeUpass( $uid,$newupass );
  }
  public static function __ADMIN_changeUpass( $uid,$newupass ) {

    $db=API::db();
    $prefix=api_g("api-table-prefix");
    $r=$db->update($prefix.'user',
      ['upass'=>$newupass],
      ['and'=>['uid'=>$uid],'limit'=>1]);
    return API::data($r);

  }

  public static  function checkUserRights( $uid, $rights ) {
    $rnow=self::getUserRights( $uid );
    if($rnow<0)return 0;
    $rights=intval($rights);
    return $rights == ($rights & $rnow) ? 1 : 0;
  }
  public static  function getUserRights( $uid ) {
    if(!self::_isIdValid($uid)) {
      return -1;
    }
    $db=API::db();
    
    $prefix=api_g("api-table-prefix");
    $rr=$db->get($prefix.'user',['rights'],
      ['uid'=>$uid ]  );

    if(count($rr)== 0) {
      return -2;
    }
    $rnow=intval($rr['rights']);
    return  $rnow;
    
  }
  /**
   *  验证签名
   *  sign = hex_md5(api+call+uid+token+timestamp)
   *  其中 token 客户端不用传给服务器，只需要传tokenid
   *  服务器
   */
  public static  function userVerify( ) {
    if(api_g('userVerify'))return api_g('userVerify');
    $uid=API::INP('uid');
    $tokenid=API::INP('tokenid');
    $timestamp=API::INP('timestamp');
    $sign=API::INP('api_signature');
    if( ! $uid || ! $tokenid || ! $timestamp || ! $sign )return false;
    return self::_signVerify( $uid,$tokenid,$timestamp,$sign );
  } 
  static  function _signVerify( $uid,$tokenid,$timestamp,$sign ) {
    //api_g('dbg-_signVerify',"$uid,$tokenid,$timestamp,$sign");
    if( ! $uid || ! $tokenid || ! $timestamp || ! $sign )return false;
    if( abs($timestamp-time()>300))return false;//仅允许5分钟以内的时间差
    $token=self::_tokGet($uid,$tokenid);
    if(!$token)return false;

    $apipara=explode('/',api_g('api'));
    $api=$apipara[1];
    $call=$apipara[2];
    $sign_srv=md5($api.$call.$uid.$token.$timestamp);
    $ok= $sign_srv == $sign;
    if($ok) {
      api_g('userVerify',['api'=>$api,'call'=>$call,'uid'=>$uid,//'token'=>$token,
        'timestamp'=>$timestamp,'sign'=>$sign]);
    }
    return $ok;
  }

  
  //=====================================
  static  function _isNameValid($name) {
    //字母，数字，下划线。下划线不能在最前面或最后面。
    if (preg_match('/^[a-zA-Z][a-zA-Z\d_]{0,30}[a-zA-Z\d]$/i', $name)) {
      return true;
    } else {
      return false;
    }
  }
  static  function _isIdValid($uid) {
    if( preg_match('/^[\d]{1,30}$/i', $uid)) {
      return true;
    }
    return false;
  }
  
  //返回值约定：
  //对象: 已存在用户的密码, id
  //0: 用户名不存在，可以注册
  //-1: 用户名非法
  static  function _getPassById($uid) {
    if(!self::_isIdValid($uid)) {
      return -1;
    }
    $db=API::db();
    
    $prefix=api_g("api-table-prefix");
    $rr=$db->get($prefix.'user',['uid','uname','upass'],
      ['uid'=>$uid ]  );

    if(count($rr)>0) {
      return $rr;
    }
    return 0;
  }
  
  //返回值约定：
  //对象: 已存在用户的密码, id
  //0: 用户名不存在，可以注册
  //-1: 用户名非法
  static  function _getPassByName($uname) {
    if(!self::_isNameValid($uname)) {
      return -1;
    }
    $db=API::db();
    
    $prefix=api_g("api-table-prefix");
    $sth = $db->pdo->prepare("SELECT `uid`,`uname`,`upass`  
         FROM `{$prefix}user` 
	       WHERE UPPER(`uname`) = UPPER(:uname) LIMIT 1");
    $sth->bindParam(':uname', $uname, PDO::PARAM_STR, 32);
    $sth->execute();
    $rr=$sth->fetchAll();
    //$ee=$sth->errorInfo();
    /*
    $r=$db->select($prefix.'user',
      ['user_pass'],
      [ 'AND'=>['uname'=>$uname],
        "LIMIT" => 1]);
        */
    if(count($rr)==1) {
      return $rr[0];
    }
    return 0;
  }
  
  // =========================================
  
  static  function _tokGen($uid, $tokenid) {
    list($usec, $sec) = explode(' ', microtime());
    srand($sec + $usec * 1000000);
    $salt=api_g("usr-salt");
    $salt_ver=$salt['version'];
    $str=substr( sha1( $salt['salt'] . rand().uniqid().$_SERVER['REMOTE_ADDR']) ,-6);//先取短点，若有需要可以取长点
    
    $day=0+date('ymd',time() );
    
    return "$uid~$day~$salt_ver~$str";
  }
  static  function _tokGet($uid,$tokenid) {
    $db=API::db();
    $prefix=api_g("api-table-prefix");
    $rr=$db->get($prefix.'token',['id','token'],
      ['and'=>['uid'=>$uid,'tokenid'=>$tokenid ],
          "LIMIT" => 1]  );

    if(isset($rr['id']) && $rr['token']) {
      return $rr['token'];
    }
    return '';
  }
  static  function _tokSave($uid,$tokenid,$tok) {
    $db=API::db();
    $prefix=api_g("api-table-prefix");
    
    $ip=$_SERVER['REMOTE_ADDR'];
    
    $rr=$db->get($prefix.'token',['id'],
      ['and'=>['uid'=>$uid,'tokenid'=>$tokenid ]]  );

    $desc=$_SERVER['REMOTE_ADDR'].'@@'.$_SERVER['HTTP_USER_AGENT'];
    if(isset($rr['id']) && $rr['id']) {
      $id=$rr['id'];
      $r=$db->update($prefix.'token',
        [ 'uid'=>$uid ,'tokenid'=>$tokenid , 'token'=>$tok , 'ip'=>$ip, 'tokenDesc'=>$desc ] ,
        [ 'AND'=>['id'=>$id],
          "LIMIT" => 1]);
    } else {
      $r=$db->insert($prefix.'token',
        [ 'uid'=>$uid ,'tokenid'=>$tokenid , 'token'=>$tok , 'ip'=>$ip, 'tokenDesc'=>$desc ] );
    }
    return $r;
  }
  
}
