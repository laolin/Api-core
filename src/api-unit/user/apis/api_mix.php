<?php
/**
 * 混合调用模块
 */
namespace NApiUnit;

class class_mix{
  /**
   * 统一入口，只允许 https 调用
   */
  public static function API($functionName, $request){
    if(!method_exists(__CLASS__, $functionName)){
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }
    if(!\DJApi\API::is_https()){
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, "请使用https");
    }
    return self::$functionName($request);
  }

  /**
   * 接口： mix/wx_code_to_token_uid
   * 用code换取用户登录票据和uid
   *
   * @request name: 公众号名称
   * @request code
   *
   * @return uid
   * @return token
   * @return tokenid
   * @return timestamp
   */
  public static function wx_code_to_token_uid($request){

    // 1. 用 code 换取 unionid
    require_once "api_wx.php";
    $json_unionid = class_wx::code_login($request);
    \DJApi\API::debug(['code_login', $json_unionid]);
    if(!\DJApi\API::isOk($json_unionid)) return $json_unionid;
    $unionid = $json_unionid['datas']['unionid'];

    // 2. 用 unionid 换取 uid
    require_once "api_bind.php";
    $request->query = ['bindtype'=>'wx-unionid', 'value'=>$unionid];
    $json_uid = class_bind::get_uid($request);
    \DJApi\API::debug(['get_uid', $json_uid]);
    if(!\DJApi\API::isOk($json_uid)) return $json_uid;
    $uid = $json_uid['datas']['uid'];

    // 3. 用 uid 换取 token
    require_once "api_user.php";
    $request->query = ['uid'=>$uid];
    $json_token = class_user::create_token($request);
    \DJApi\API::debug(['create_token', $json_token]);
    if(!\DJApi\API::isOk($json_token)) return $json_token;

    $json_token['datas']['uid'] = $uid;

    return $json_token;
  }


}
