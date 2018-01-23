<?php

// ---------------------------- 密码加密 ----------------------------
function MD5PASSWORD($password){
    return md5($password . PASSWORD_APPEND);
}
// ---------------------------- 密码异或 ----------------------------
function XORMD5($a, $b){
    $table = "0123456789ABCDEF";
    $code = [];for($i=0; $i<16; $i++)$code[$table[$i]] = $i;
    $c = "0123456789ABCDEF0123456789ABCDEF";
    for($i=0; $i<32; $i++){
      $c[$i] = $table[ $code[$a[$i]] ^ $code[$b[$i]] ];
    }
    return $c;
}



class CUserToken{
  const table_token = "z_user_token";

  /**
   * 获取建一个 token
   * 如果有过时的token, 将被覆盖，否则新建一个
   */
  static function strToken(){
    $token = "1234567890123456789012345678901234567890123456789012345678901234";
    $c = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    for($i=0; $i<64; $i++){
      $token[$i] = $c[ rand(0,61) ];
    }
    return $token;
  }

  /**
   * 获取建一个 token
   * 如果有过时的token, 将被覆盖，否则新建一个
   */
  static function newToken($userid, $s = 7200){
    $db = API::db();
    $t = time();
    $token = self::strToken();
    $row = ["userid"=>$userid, 'token'=>$token, 'time_expire'=>$t + $s, 'extend_seconds'=>$s];
    $n = $db->update(mymedoo::table('z_user_token'), $row, ["AND"=>["time_expire[<]"=>$t], "LIMIT"=>1]);
    api_g('tokenExtendSecond', $s);
    if($n == 1) return $token;
    $db->insert(mymedoo::table('z_user_token'), $row);
    return $token;
  }

  /**
   * 延长一个 token
   * 如果存在，则延时，否则，返回错误
   */
  static function extendToken($token, $s = 7200){
    if($s <= 0) $s = 7200;
    $db = API::db();
    $t = time();
    $n = $db->update(mymedoo::table('z_user_token'), ["time_expire"=>$t + $s], ["AND"=>["token"=>$token, "time_expire[>]"=>$t]]);
    if($n){
      api_g('tokenExtendSecond', $s);
    }
    return $n == 1;
  }

  /**
   * 延长一个 token
   * 如果存在，则延时，否则，返回错误
   */
  static function getUserByToken($token){
    $db = API::db();
    $t = time();
    $tokenRow = $db->get(mymedoo::table('z_user_token'), ['userid', 'extend_seconds'], ["AND"=>["token"=>$token, "time_expire[>]"=>$t]]);
    $userid = $tokenRow['userid'];
    if(!$userid)return (new CSignUser(false))->setMsg(1001, "未登录", ['DB'=>$db->getShow()]);
    $user = new CSignUser(["id"=>$userid]);
    if($user->me['id']){
      self::extendToken($token, $tokenRow['extend_seconds']);
    }
    api_g('tokenExtendSecond', $tokenRow['extend_seconds']);
    api_g('userid', $userid);
    return $user;
  }
}



class CSignUser{
  public $me;
  public $msg;

  /**
   * 构造
   */
  public function __construct($where) {
    if(!is_array($where)){
      return;
    }
    $this->me = API::db()->get(mymedoo::table('z_user'), ["id", "password", "name", "parentid", "mobile", "attr"], $where);
    if(!is_array($this->me)){
      $this->msg = API::msg(1001, "用户名或密码错误", ["me"=>$this->me]);//用户不存在
      return;
    }
  }

  /**
   * 保存个人数据
   */
  function saveDatas($newValues){
    $values = [];
    foreach($newValues as $k=>$v){
      switch($k){
        case "nick":
        case "name":
        case "mobile":
        case "password":
          $values[$k] = $v;
          break;
      }
    }
    return API::db()->update(mymedoo::table('z_user'), $values, ["id"=>$this->me['id']]);
  }


  /**
   * 添加用户
   * 返回一个新用户id
   */
  static function createUser($userData){
    $db = API::db();
    if(!$userData['password'])return false;
    $row = [];
    $row['password'] = $userData['password'];
    $row['nick'    ] = $userData['nick'    ];
    $row['name'    ] = $userData['name'    ];
    $row['mobile'  ] = $userData['mobile'  ];
    $userId = $db->insert(mymedoo::table('z_user'), $row);
    return $userId;
  }



  /**
   * 设置错误
   */
  public function setMsg($code, $msg, $arr=false){
    $this->msg = API::msg($code, $msg, $arr);
    return $this;
  }


  /**
   * 是否错误
   */
  public function is_error(){
    if(!$this->msg)return false;
    return API::is_error($this->msg);
  }



  /**
   * 验证签名
   */
  public function checkSign(){
    //只对post签名：
    //if(strtoupper($_SERVER['REQUEST_METHOD']) == 'GET')return true;
    if(!isset($_REQUEST['sign'    ]))return false;
    if(!isset($_REQUEST['timespan']))return false;
    $str = "{$this->me['password']}{$_REQUEST['timespan']}";
    //echo "{$this->me['password']},{$_REQUEST['timespan']}, md5=" . strtoupper(md5($str));
    return strtoupper(md5($str)) == $_REQUEST['sign'] ;
  }



}


