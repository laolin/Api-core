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
   * @request uid: uid或[uid], 最多1000个，但每次保证更新的最多100个
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

    // 限制1000个uid
    if(!is_array($uid))$uid = [$uid];
    if(is_array($uid) && count($uid) > 1000){
      $uid = array_slice($uid, 0, 1000);
    }
    $uid_1000 = $uid;

    $appName = $request->query["appname"];
    list($appid, $secret) = WxTokenBase::appid_appsec($appName);
    if(!$appid){
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, '无微信配置');
    }

    $db = CDbBase::db();
    $time = time();
    \DJApi\API::debug("time=$time");

    // 读取所有绑定，包括 wx-openid 和 wx-unionid
    $AND = [
      "uid" => $uid_1000,
      "OR"=>[
        "bindtype" => 'wx-unionid',
        "AND"=>[
          "bindtype" => 'wx-openid',
          "param1" => $appid
        ]
      ]
    ];
    $binds = $db->select(CDbBase::table('bind'), ['uid', 'bindtype', 'value'], ['AND' => $AND]);
    $binds_openid = array_filter($binds, function($row){
      return $row['bindtype'] =='wx-openid';
    });
    $binds_unionid = array_filter($binds, function($row){
      return $row['bindtype'] =='wx-unionid';
    });
    // \DJApi\API::debug(['获取绑定', $binds, $db->getShow()]);

    // 读取所有满足条件的 openid 绑定
    $binds_openid = array_filter($binds, function($row){
      return $row['bindtype'] =='wx-openid';
    });
    // \DJApi\API::debug(['获取 binds_openid', $binds_openid]);

    // 所有满足条件的 openid
    $openids = array_map(function($row){
      return $row['value'];
    }, $binds_openid);
    //去重
    $openids = array_unique($openids);
    // \DJApi\API::debug(['获取 openids', $openids]);

    // 数据库中已有的数据
    $wxUser = $db->select(CDbBase::table('wx_user'), '*', ["AND"=>['openid' => $openids], "ORDER"=>["timeupdate"=>"DESC"]]);
    $wxUser = array_map(function($row){
      if(!$row['timeupdate']) $row['timeupdate'] = 0;
      return $row;
    }, $wxUser);
    // \DJApi\API::debug(['获取微信 wxUser = ', $wxUser, $db->getShow()]);

    // 数据库中已有的 openid
    $openid_found = array_map(function($row){
      return $row['openid'];
    }, $wxUser);
    // \DJApi\API::debug(['数据库中已有的 openid', $openid_found]);

    // 长期未更新的 wxUser
    $wxUser_long_pass = array_values(array_filter($wxUser, function($row){
      $time = time();
      return $time - $row['timeupdate'] > 24 * 3600;
    }));
    // \DJApi\API::debug(['长期未更新的 wxUser', $wxUser_long_pass]);

    // 长期未更新的 openid
    $openid_long_pass = array_values(array_map(function($row){
      return $row['openid'];
    }, $wxUser_long_pass));
    //去重
    $openid_long_pass = array_unique($openid_long_pass);
    // \DJApi\API::debug(['长期未更新的 openid', $openid_long_pass]);

    // 数据库中没有的 openid
    $openid_unfound = array_diff($openids, $openid_found);
    // \DJApi\API::debug(['数据库中没有的 openid', $openid_unfound]);
    

    // 如果不足100个，分析最旧的数据的 openid
    $maxReget = 100;
    if(count($openid_unfound) < $maxReget) {
      $openid_more = array_slice($openid_long_pass, count($openid_unfound) - $maxReget);
      $openid_renew = array_merge($openid_unfound, $openid_more);
    }
    else {
      $openid_renew = array_slice($openid_unfound, 0, $maxReget);
    }
    // \DJApi\API::debug(['最旧 openids', $openid_renew]);

    // 获取 100 个 openid 对应的信息
    if(count($openid_renew)){
      $wxUser_renew = CWxBase::getWxUserBatchget($openid_renew, $appid, $secret);
      if(!is_array($wxUser_renew)) $wxUser_renew = [];
      \DJApi\API::debug(['批量获取微信', $wxUser_renew]);
    }
    else{
      $wxUser_renew = [];
      \DJApi\API::debug('没有需获取的微信');
    }

    // 保存这100个，包括未关注的
    if(count($wxUser_renew)){
      $save_datas = array_map(function($row){
        $newRow = ['timeupdate' => time()];
        $fields = ["subscribe", "openid", "nickname", "sex", "language", "city", "province", "country", "headimgurl", "subscribe_time", "unionid", "remark", "groupid"];
        foreach($fields as $k){
          $newRow[$k] = isset($row[$k]) ? $row[$k]: '';
        }
        return $newRow;
      }, $wxUser_renew);
      $update_datas = [];
      $new_datas = [];
      foreach($save_datas as $row){
        if(in_array($row['openid'], $openid_found)) {
          $update_datas[] = $row;
        }
        else {
          $new_datas[] = $row;
        }
      }
      // 更新的行
      if(count($update_datas) > 0) {
        $n = 0;
        foreach($update_datas as $row){
          $n += $db->update(CDbBase::table('wx_user'), $row, ["openid"=>$row['openid']]);
        }
        \DJApi\API::debug(["更新了 $n 行", $db->getShow()]);
      }
      // 新增的行
      if(count($new_datas) > 0){
        $n = $db->insert(CDbBase::table('wx_user'), $new_datas);
        \DJApi\API::debug(['插入数据库', $new_datas, $n, $db->getShow()]);
      }
    }

    // 生成最新的返回数据
    $openid_uid = [];
    $unionid_uid = [];
    foreach($binds as $row){
      if($row['bindtype'] =='wx-openid'){
        $openid_uid[$row['value']] = $row['uid'];
      }
      if($row['bindtype'] =='wx-unionid'){
        $unionid_uid[$row['value']] = $row['uid'];
      }
    }
    // \DJApi\API::debug(['uid 索引', $openid_uid, $unionid_uid]);
    $list = [];
    // 从最近更新
    foreach($wxUser_renew as $row){
      $uid = $openid_uid[$row['openid']];
      if(!in_array($uid, $uid_1000)) continue;
      // 有关注的，才有数据
      if($uid && !$list[$uid] && $row['subscribe']){
        $row['uid'] = $uid;
        $list[$uid] = $row;
      }
    }
    foreach($wxUser as $row){
      // 要有头像或呢称
      if(!$row['headimgurl'] && !$row['nickname']) continue;
      $uid = $openid_uid[$row['openid']];
      if(!in_array($uid, $uid_1000)) continue;
      if($uid && !$list[$uid]){
        $row['uid'] = $uid;
        $list[$uid] = $row;
      }
      $uid = $openid_uid[$row['unionid']];
      if($uid && !$list[$uid]){
        $row['uid'] = $uid;
        $list[$uid] = $row;
      }
    }
    // \DJApi\API::debug(['得到列表 list = ', $list]);
    return \DJApi\API::OK(['list'=>array_values($list)]);



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
