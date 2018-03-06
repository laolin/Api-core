<?php
namespace NApiUnit;

/**
 * 基础类
 */
class CUser {
  
  /**
   *  验证签名
   *  sign = hex_md5(api+call+uid+token+timestamp) 或 hex_md5(token+timestamp)
   *  其中 token 客户端不用传给服务器，只需要传tokenid
   *  服务器
   */
  public static  function verify_token($request) {
    $query = $request->query;
    $tokenid   = $query['tokenid'      ];
    $timestamp = $query['timestamp'    ];
    $sign      = $query['sign'];
    if(!$sign)$sign = $query['api_signature']; // 为了兼容 laolin ,
    if( !$tokenid || !$timestamp || !$sign ){
      \DJApi\API::debug(['签名错误', ['tokenid'=>$tokenid,'timestamp'=>$timestamp,'sign'=>$sign]]);
      return \DJApi\API::error(\DJApi\API::E_NEED_LOGIN, '签名错误');
    }

    $time = time();
    if( abs($timestamp - $time) > 300){
      //仅允许5分钟以内的时间差
      return \DJApi\API::error(\DJApi\API::E_NEED_LOGIN, '时间不对', ["timestamp" =>$time]);
    }

    $db = CDbBase::db();
    $tokenRow = $db->get(CDbBase::table('token'), ['uid', 'token'], ['AND'=>['tokenid' => $tokenid]]);
    if(!$tokenRow){
      return \DJApi\API::error(\DJApi\API::E_NEED_LOGIN, '签名失败');
    }

    $api   = $request->api;
    $call  = $request->call;
    $uid   = $tokenRow['uid'  ];
    $token = $tokenRow['token'];
    $sign_true == md5($api.$call.$uid.$token.$timestamp);
    if($sign != $sign_true) $sign_true = md5($token.$timestamp);
    if($sign != $sign_true){
      \DJApi\API::debug(['签名非法（危险！）', ['请求'=>$sign, 'b'=>'1'.$sign_true.'a', 'a'=>$token.$timestamp]]);
      return \DJApi\API::error(\DJApi\API::E_NEED_LOGIN, '签名非法');
    }
    return \DJApi\API::OK(['uid' => $uid]);
  }

  /**
   * 根据uid, 生成票据并返回
   * @param uid
   *
   * @return token
   * @return tokenid
   * @return timestamp
   */
  public static function create_token($uid){
    $timestamp = time();
    $token = \DJApi\API::createNonceStr(64);

    $db = CDbBase::db();
    $tokenid = $db->insert(CDbBase::table('token'), [
      'uid' => $uid,
      'token' => $token,
      'token_time' => $timestamp
    ]);
    if(!$tokenid){
      \DJApi\API::debug(['生成票据失败', $db->getShow()]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '生成票据失败');
    }
    return \DJApi\API::OK([
      'tokenid'   => $tokenid,
      'token'     => $token,
      'token_time'=> $timestamp
    ]);
  }

}

