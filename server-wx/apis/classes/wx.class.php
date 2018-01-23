<?php

use DJApi\DB;
use DJApi\API;
use DJApi\Configs;

class WX{

  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃              获取 Token                                                      ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function GetToken($db = false){
    $appid  = Configs::get('WX_APPID');
    $secret = Configs::get('WX_APPSEC');
    return self::GetWxToken($appid, $secret, $db);
  }
  static function GetWxToken($appid, $secret, $db = false){
    $wx_token_db = Configs::get('wx_token_db');
    $dbParams = $wx_token_db['medoo'];
    $tableName = $wx_token_db['tableName']; if(!$tableName) $tableName = 'g_settings';
    if($db===false)$db = new DB($dbParams);

    $row = $db->get($tableName, ["v1", "v2"], ["AND"=>[ "k1"=>'access_token', "k2"=>$appid]]);
    $t_now = time();
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局Token仍然有效
    }

    //获取新的 全局Token:
    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential"
           . "&appid=" . $appid . "&secret=" . $secret;
    $r = API::httpGet($url);

    $dick_token = json_decode($r);
    if(isset($dick_token->errcode)) return "";
    //得到了：
    $token = $dick_token->access_token;
    if(strlen($token)<10)return "";
    //写入数据库，以后可以再用：
    if ($row){
      $db->update($tableName, ["v1"=>$token,"v2"=>$t_now], ["AND"=>["k1"=>'access_token', "k2"=>$appid]]);
    }else{
      $db->insert($tableName, ["v1"=>$token,"v2"=>$t_now,"k1"=>'access_token', "k2"=>$appid]);
    }
    return $token;
  }
  static function GetJsApiTicket($db = false){
    $wx_token_db = Configs::get('wx_token_db');
    $dbParams = $wx_token_db['medoo'];
    $tableName = $wx_token_db['tableName'];
    $appid  = Configs::get('WX_APPID');
    $secret = Configs::get('WX_APPSEC');
    if($db===false)$db = new medoo($dbParams);

    $row = $db->get($tableName, ["v1", "v2"], ["AND"=>[ "k1"=>'jsapi_ticket', "k2"=>$appid]]);
    $t_now = time();
    if ($row && $row['v1']){
      $t_old = $row["v2"] + 0;
      if ($t_now - $t_old < 7000) return $row["v1"];//旧的全局ticket仍然有效
    }

    //获取新的 全局ticket:
    $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".self::GetToken($db);
    $res = json_decode(API::httpGet($url));
    if(!isset($res->errcode) || $res->errcode != 0) return "";
    $ticket = $res->ticket;
    if (!$ticket) return "";

    //写入数据库，以后可以再用：
    if ($row){
      $db->update($tableName, array("v1"=>$ticket,"v2"=>$t_now), ["AND"=>["k1"=>'jsapi_ticket', "k2"=>$appid]]);
    }else{
      $db->insert($tableName, ["v1"=>$ticket,"v2"=>$t_now,"k1"=>'jsapi_ticket', "k2"=>$appid]);
    }
    return $ticket;
  }



  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             发送模板消息                                                     ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function SendTPL($openids, $tplName, $data, $url){
    if(substr($url, 0, 1) == '#'){
      $url = $_SERVER['HTTP_REFERER'] . $url;
    }
    foreach($GLOBALS["WX_TPL"] as $name=>$tpl){
      if($name!=$tplName)continue;
      //找到模板了，整理一下openids：
      if(!is_array($openids))$openids = [$openids];
      if($openids[0] > 200000){
        $openids = DB::db()->select(DB::table("z_user_bind"), "openid", ["userid"=>$openids]);
        if(!is_array($openids)) return "no user";//API::db()->getShow();
      }
      //开始发送：
      $r[] = 'sending...';
      $send_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . self::GetToken();
      foreach($openids as $openid){
        $openid = trim($openid,"*");
        if(strlen($openid)<10)continue;
        $D = [
          "touser"=> $openid,
          "template_id"=> $tpl["template_id"],
          "topcolor"=> "#FF8800",
          "url"=> $url,
          "topcolor"=>"#FF0000",
          "data"=>[]
        ];
        $D["data"]['first' ] = ["value"=>$data[0], "color"=>$tpl["first" ]];
        $D["data"]['remark'] = ["value"=>$data[2], "color"=>$tpl["remark"]];
        $i = 0; foreach($tpl["data"] as $k=>$color){
          $D["data"][$k] = ["value"=>$data[1][$i++], "color"=>$color];
        }
        $json = urldecode(cn_json($D));//API::JSON_from($D);
        $res = API::httpPost($send_url, $json);

        //API::log_result('', "发送模板消息" . cn_json(['json'=>$json, 'res'=>$res, 'data'=>$data]));
        //$r[] = ;
      }
      return $r;
    }
    return 'error';
  }



  /**
   * 从微信接口，获取一个 json 数据
   */
  static function getInterfaceJson($subUrl){
    $url = "https:" . "//api.weixin.qq.com/{$subUrl}";
    $res = API::httpGet($url);
    return json_decode($res, true);
  }


  /**
   * 根据 code, 从微信网页静默授权，自动更新微信信息
   *
   * 如果在 48 小时之内已有更新，则不进一步处理
   * 否则，重新拉取头像等，保存
   */
  static function getUserinfoAndAutoSave($code){
    $db = DB::db();
    $timespan = time();
    $appid  = Configs::get("WX_APPID" );
    $secret = Configs::get("WX_APPSEC");

    // 通过code获取access_token
    //https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code
    $step1 = WX::getInterfaceJson("sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code");

    $openid       = $step1['openid'];
    $unionid      = $step1['unionid'];
    $access_token = $step1['access_token'];
    // 拉取失败
    if(!$openid){
      return ['error'=> $step1];
    }

    // 获取缓存
    $wxUser = $db->get(DB::table('wx_user'), "*", ["openid"=>$openid]);
    // 缓存有效：
    if($wxUser && $wxUser['rqupdate'] > $timespan - 48*3600 ){
      return $wxUser;
    }

    // 获取用户个人信息（UnionID机制）
    // 因为网页登录时采用静默授权
    $step3 = WX::getInterfaceJson("cgi-bin/user/info?access_token=" .self::GetToken() . "&openid=$openid&lang=zh_CN");

    // 获取失败，就返回上一个
    if(!$step3['openid']){
      return $wxUser ? $wxUser : $step1;
    }

    // 保存
    self::saveWxInfo($step3, "openid");
    return $step3;
  }


  /**
   * 根据 code, 从微信第三方平台获取的信息，自动更新微信信息
   *
   * 如果在 48 小时之内已有更新，则不进一步处理
   * 否则，重新拉取头像等，保存
   */
  static function getUnionId3AndAutoSave($code){
    $db = DB::db();
    $timespan = time();
    $appid  = Configs::get("WX_APPID3" );
    $secret = Configs::get("WX_APPSEC3");

    // 通过code获取access_token
    //https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code
    $step1 = WX::getInterfaceJson("sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code");

    $openid       = $step1['openid'];
    $unionid      = $step1['unionid'];
    $access_token = $step1['access_token'];
    // 拉取失败
    if(!$openid){
      return [];
    }

    // 获取缓存
    $wxUser = $db->get(DB::table('wx_user'), "*", ["unionid"=>"$unionid"]);
    // 缓存有效：
    if($wxUser && $wxUser['rqupdate'] > $timespan - 48*3600 ){
      return $wxUser;
    }

    // 刷新access_token有效期
    // api.weixin.qq.com/sns/oauth2/refresh_token?appid=APPID&grant_type=refresh_token&refresh_token=REFRESH_TOKEN
    if($step1['expires_in'] < 300){
      $step2 = WX::getInterfaceJson("sns/oauth2/refresh_token?appid=$appid&grant_type=refresh_token&refresh_token={$step1['refresh_token']}");
      if($step2['access_token']){
        $access_token = $step2['access_token'];
      }
    }

    // 拉取用户信息(需scope为 snsapi_userinfo)
    //https://api.weixin.qq.com/sns/userinfo?access_token=ACCESS_TOKEN&openid=OPENID
    $step3 = WX::getInterfaceJson("sns/userinfo?access_token=$access_token&openid=$openid");

    // 获取失败，就返回上一个
    if(!$step3['unionid']){
      return $wxUser ? $wxUser : $step1;
    }

    // 保存
    self::saveWxInfo($step3, "unionid");
    return $step3;
  }


  /**
   * 保存微信信息
   *
   * @$new_wxinfo：新的用户信息
   * @$where：两种情况["openid"=>"$openid"] 或 ["unionid"=>"$unionid"]
   *      使用 unionid 时，openid 内容不会被保存，以便第三方授权时使用本函数
   */
  static function saveWxInfo($new_wxinfo, $byField){
    $db = DB::db();
    //没有具体信息的，不保存
    if(!$new_wxinfo || !$new_wxinfo['openid'] || !$new_wxinfo['headimgurl']){
      return "";
    }

    //条件指定不合法
    if($byField != 'openid' && $byField != 'unionid'){
      return "";
    }
    $where = [$byField=>$new_wxinfo[$byField]];

    // 要保存的项目
    $data = [ 'rqupdate' => time() ];
    $fields = [/*"subscribe", "openid",*/ "nickname", "sex", "language", "city", "province", "country", "headimgurl", "subscribe_time", "unionid", "remark", "groupid"];
    // 如果条件中有 openid, 才保存 openid 这一项
    if($where['openid']){
      $fields[] = 'openid';
      $fields[] = 'subscribe';
      $data['isgz'] = $new_wxinfo['subscribe'] + 0  == 0 ? "已取消" : "已关注";
    }
    // 生成数据
    foreach($fields as $k){
      $data[$k] = $new_wxinfo[$k];
    }
    // 有旧数据的，覆盖
    if($db->has(DB::table('wx_user'), $where)){
      $db->update(DB::table('wx_user'), $data, $where);
    }
    // 没有旧数据的，新建
    else{
      $db->insert(DB::table('wx_user'), $data);
    }
    // 返回生成的数据
    return $data;
  }


  // 根据OPENID获取用户头像等信息
  static function getWxInfoByOpenid($openid){
    $res = self::read_WX_userinfo($openid);
    return json_decode($res, true);
  }
  static function read_WX_userinfo($openid, $db=false){
    $url = "https:/"."/api.weixin.qq.com/cgi-bin/user/info?access_token=" .self::GetToken()
          . "&openid=" . $openid
          . "&lang=zh_CN";
    return API::httpGet($url);
  }

}
