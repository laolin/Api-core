<?php
namespace NApiUnit;

/**
 * 基础类
 */
class CBind {
  static $bindtype = ['wx-openid', 'wx-unionid', 'mobile'];

  /**
   * 绑定用户[微信openid/微信unionid/手机号]
   * @param uid
   * @param bindtype: 绑定类型[mobile/wx-openid/wx-unionid/uidparent]
   * @param value: 绑定的值
   * @param param1: 绑定子类型1, 可选
   * @param param2: 绑定子类型2, 可选
   *
   * @return 是否成功
   */
  public static function bind($query){
    $uid      = $query["uid"     ];
    $bindtype = $query["bindtype"];
    $value    = $query["value"   ];
    $param1   = $query["param1"  ];
    $param2   = $query["param2"  ];
    if(!$uid || !$bindtype || !$value){
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效1");
    }
    if(!in_array($bindtype, self::$bindtype)){
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效2");
    }

    $data = [
      "uid"      => $uid     ,
      "bindtype" => $bindtype,
      "value"    => $value   ,
    ];
    if($param1)$data['param1'] = $param1;
    if($param2)$data['param2'] = $param2;
    $db = CDbBase::db();
    $bindid = $db->insert(CDbBase::table('bind'), [
      "uid"      => $uid     ,
      "bindtype" => $bindtype,
      "value"    => $value   ,
      "param1"   => $param1  ,
      "param2"   => $param2  
    ]);
    if(!$bindid){
      \DJApi\API::debug(['绑定用户失败', $db->getShow()]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '绑定用户');
    }
    return \DJApi\API::OK(['bindid' => $bindid]);
  }

  /**
   * 获取指定uid绑定情况，同时包含[微信openid/微信unionid/手机号]
   * @query uid
   * @param bindtype: 可选, 绑定类型[mobile/wx-openid/wx-unionid/uidparent]
   *
   * @return binds: 数组
   */
  public static function get_bind($query){
    $uid      = $query["uid"     ];
    $bindtype = $query["bindtype"];
    $AND = ['uid' => $uid];
    if($bindtype) $AND['bindtype'] = $bindtype;
    $db = CDbBase::db();
    $binds = $db->select(CDbBase::table('bind'), ["bindtype", "value", "param1", "param2"], ['AND' => $AND]);
    return \DJApi\API::OK(['binds' => $binds]);
  }

  /**
   * 根据[微信openid/微信unionid/手机号], 获取用户uid
   * @query bindtype: 绑定类型[mobile/wx-openid/wx-unionid/uidparent]
   * @query value: 绑定的值
   * @query param1: 绑定子类型1, 可选
   * @query param2: 绑定子类型2, 可选
   *
   * @return uid
   */
  public static function get_uid($query){
    $bindtype = $query["bindtype"];
    $value    = $query["value"   ];
    $param1   = $query["param1"  ];
    $param2   = $query["param2"  ];
    if(!$bindtype || !$value){
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "参数无效1");
    }

    $db = CDbBase::db();
    $AND = [
      "bindtype" => $bindtype,
      "value"    => $value   ,
    ];
    if($param1) $AND['param1'] = $param1;
    if($param2) $AND['param2'] = $param2;
    $uid = $db->get(CDbBase::table('bind'), 'uid', ['AND' => $AND]);
    if(!$uid){
      \DJApi\API::debug(['获取用户uid', $db->getShow()]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '获取用户uid');
    }
    return \DJApi\API::OK(['uid' => $uid]);
  }

  /**
   * 根据[微信openid/微信unionid/手机号], 获取用户uid
   * @query bindtype: 绑定类型[mobile/wx-openid/wx-unionid/uidparent]
   * @query uid: 绑定的值
   * @query param1: 绑定子类型1, 可选
   * @query param2: 绑定子类型2, 可选
   *
   * @return uid
   */
  public static function get_binds($query){
    $bindtype = $query["bindtype"];
    $uid      = $query["uid"     ];
    $value    = $query["value"   ];
    $param1   = $query["param1"  ];
    $param2   = $query["param2"  ];
    if(!$uid && !$value && !$bindtype){
      \DJApi\API::debug(['获取绑定, 参数无效', $query]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "绑定查询, 参数无效");
    }

    $db = CDbBase::db();
    $AND = [];
    if($uid     ) $AND['uid'     ] = $uid     ;
    if($bindtype) $AND['bindtype'] = $bindtype;
    if($value   ) $AND['value'   ] = $value   ;
    if($param1  ) $AND['param1'  ] = $param1  ;
    if($param2  ) $AND['param2'  ] = $param2  ;
    $binds = $db->select(CDbBase::table('bind'), ['uid', 'value'], ['AND' => $AND]);
    if(!is_array($binds)){
      \DJApi\API::debug(['获取绑定', $db->getShow(), $binds]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '获取绑定, 失败');
    }
    return \DJApi\API::OK(['binds' => $binds]);
  }

}

