<?php
namespace NApiUnit;

/**
 * 基础类
 */
class CWxBase {

  /**
     * 从微信接口，获取一个 json 数据
   */
  static function callWxApi($subUrl){
    $url = "https:" . "//api.weixin.qq.com/{$subUrl}";
    $res = \DJApi\API::httpGet($url);
    return json_decode($res, true);
  }

  /**
   * 通过code获取[access_token, openid, unionid]
   */
  public static function code2unionid($code, $appid, $secret){
    // step1: 通过code获取[access_token, openid, unionid]
    $step1 = self::callWxApi("sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code");
    $openid       = $step1['openid'];
    $unionid      = $step1['unionid'];
    $access_token = $step1['access_token'];
    // 拉取失败
    if(!$openid){
      \DJApi\API::debug(['拉取失败', $step1]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "拉取失败", [$step1]);
    }
    return \DJApi\API::OK($step1);
  }


  /**
   * 用openid获取用户个人信息（UnionID机制）
   */
  public static function getWxUser($openid, $appid, $secret){
    $token = WxTokenBase::GetWxToken($appid, $secret);
    $wxUser = self::callWxApi("cgi-bin/user/info?access_token=$token&openid=$openid&lang=zh_CN");
    return $wxUser;
  }

  /**
   * 保存用户个人信息
   */
  public static function saveWxInfo($new_wxinfo, $byField){
    $db = CDbBase::db();

    //条件指定不合法
    if($byField != 'openid' && $byField != 'unionid'){
      return "";
    }
    $where = [$byField=>$new_wxinfo[$byField]];

    // 要保存的项目
    $data = [ 'timeupdate' => time() ];
    $fields = [/*"subscribe", "openid",*/ "nickname", "sex", "language", "city", "province", "country", "headimgurl", "subscribe_time", "unionid", "remark", "groupid"];
    // 如果条件中有 openid, 才保存 openid 这一项
    if($byField == 'openid'){
      $fields[] = 'openid';
      $fields[] = 'subscribe';
    }
    // 生成数据
    foreach($fields as $k){
      $data[$k] = $new_wxinfo[$k];
    }
    // 有旧数据的，覆盖
    if($db->has(CDbBase::table('wx_user'), $where)){
      $db->update(CDbBase::table('wx_user'), $data, $where);
    }
    // 没有旧数据的，新建
    else{
      $db->insert(CDbBase::table('wx_user'), $data);
    }
    // 返回生成的数据
    return $data;
  }

  /**
   * 根据[ openid/unionid ]，获取微信呢称、头像等
   * @param openid/unionid: openid优先
   */
  public static function wx_info($openid, $unionid){
    $db = CDbBase::db();
    $AND = $openid ? ['openid'=>$openid] : ['unionid'=>$unionid];
    $wxUser = $db->get(CDbBase::table('wx_user'), '*', ['AND' => $AND]);
    return \DJApi\API::OK(['wxUser'=>$wxUser]);
  }

  /**
   * 根据[ openid/unionid ]，获取多个微信呢称、头像等
   * @param values: openid/unionid的值
   * @param valueType: 根据openid或unionid来查询
   */
  public static function wx_infos($values, $valueType){
    $db = CDbBase::db();
    $AND = [$valueType=>$values];
    $wxUser = $db->select(CDbBase::table('wx_user'), '*', ['AND' => $AND, "GROUP"=>'unionid']);
    \DJApi\API::debug(['获取多个微信', $db->getShow(), $wxUser]);
    return \DJApi\API::OK(['list'=>$wxUser]);
  }
}

