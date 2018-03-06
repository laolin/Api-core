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
    $appname = $request->query["name"];
    if(!$appname)return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效1", [$request]);
    list($appid, $secret) = WxTokenBase::appid_appsec($appname);

    // 1. 用 code 换取 unionid
    require_once "api_wx.php";
    $json_unionid = class_wx::code_login($request);
    \DJApi\API::debug(['code_login', $json_unionid]);
    if(!\DJApi\API::isOk($json_unionid)) return $json_unionid;
    $unionid = $json_unionid['datas']['unionid'];
    $openid = $json_unionid['datas']['openid'];

    // 2. 用 unionid 换取 uid
    $query_uid = ['bindtype'=>['wx-unionid', 'wx-openid'], 'value'=>[$unionid, $openid]];
    $json_uid = CBind::get_uid($query_uid);
    \DJApi\API::debug(['get_uid', $json_uid]);
    $uid = $json_uid['datas']['uid'];
    if(!$uid){
      // 未绑定的，新建用户
      $uid = CUser::create_uid();
      CBind::bind(['uid'=>$uid, 'bindtype'=>'wx-openid', 'param1'=>$appid, 'param2'=>$appname, 'value'=>$openid]);
      CBind::bind(['uid'=>$uid, 'bindtype'=>'wx-unionid', 'param1'=>$appid, 'param2'=>$appname, 'value'=>$unionid]);
    }

    // 3. 用 uid 换取 token
    $json_token = CUser::create_token($uid);
    \DJApi\API::debug(['create_token', $json_token]);
    if(!\DJApi\API::isOk($json_token)) return $json_token;

    $json_token['datas']['uid'] = $uid;

    return $json_token;
  }



  /**
   * 接口： mix/wx_infos
   * 根据[uid]，获取微信呢称、头像等
   * @request uid: uid或[uid]
   * @request bindtype: 'wx-openid'(默认)/'wx-unionid'
   * @request param1: 绑定子类型1, 可选
   * @request param2: 绑定子类型2, 可选
   */
  public static function wx_infos($request){
    $uid      = $request->query["uid"     ];
    $bindtype = $request->query["bindtype"];
    $param1   = $request->query["param1"  ];
    $param2   = $request->query["param2"  ];
    if(!$bindtype) $bindtype = 'wx-openid';
    $bind = substr($bindtype, 3);

    // 读取绑定
    $bindsJson = CBind::get_binds(['uid'=>$uid, "bindtype"=>$bindtype, "param1"=>$param1, "param2"=>$param2]);
    if(!\DJApi\API::isOk($bindsJson)){
      return $bindsJson;
    }
    $binds = $bindsJson['datas']['binds'];
    $values = array_map(function($row){return $row['value'];}, $binds);

    // 读取微信头像等
    $wxInfoJson = CWxBase::wx_infos($values, $bind);
    $wxInfo = $wxInfoJson['datas']['list'];

    // 两者合并
    $reget_uid = \DJApi\FN::array_column($binds, 'uid', 'value');
    foreach($wxInfo as $k=>$row){
      $wxInfo[$k]['uid'] = $reget_uid[$row[$bind]];
    }
    return \DJApi\API::OK(['list'=>$wxInfo]);
  }
}
