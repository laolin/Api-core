<?php
// ================================
/*
*/
namespace MyClass;

class CWxBase{

  /** 获取微信信息
   * @param uid: 用户id，可为数组或单个用户id
   * 返回：
   * @return 微信信息数组
   */
  static function getWxInfo($uid) {

    $wxInfoJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", ['uid' => $uid]);
    \DJApi\API::debug(['从独立服务器获取微信信息', $wxInfoJson]);
    if(!\DJApi\API::isOk($wxInfoJson)){
      return $wxInfoJson;
    }
    $wxInfo = $wxInfoJson['datas']['list'];

    // 两者合并
    $list = [];
    foreach($wxInfo as $k=>$row){
      $list[] = [
        'headimgurl'=>$row['headimgurl'],
        'nickname'  =>$row['nickname'  ],
        'uidBinded' =>$row['uid']
      ];
    }
    return $list;
  }

  /**
   * 获取多组多人的 openid
   */
  public static function openidgroups($query) {
    $uidGroups = $query['uid'];
    $appid = $query['appid'];
    $uids = [];
    // 合并
    foreach($uidGroups as $groupName => $uid){
      if(!is_array($uid)) $uid = [$uid];
      $uids = array_merge($uids, $uid);
    }
    // 去重
    $uids = array_unique($uids);
    // 获取所有的 openid
    $binds = \DJApi\API::post(SERVER_API_ROOT, "user/bind/get_bind", [
      'uid'   => $uids,
      'bindtype' => 'wx-openid',
      'param1' => $appid,
      'fields' => ['uid', 'value(openid)']
    ]);
    \DJApi\API::debug(['获取 openid 绑定', $binds]);

    // 再分组
    $R = [];
    $uid2openid = \DJApi\FN::array_column($binds['datas']['binds'], 'openid', 'uid');
    \DJApi\API::debug(['拭回数组', $uid2openid, $uidGroups]);
    foreach($uidGroups as $groupName => $uidGroup){
      if(is_array($uidGroup)){
        foreach($uidGroup as $uid){
          $R[$groupName][] = $uid2openid[$uid];
        }
        // 去重
        $R[$groupName] = array_unique($R[$groupName]);
      }
      else{
        $R[$groupName] = $uid2openid[$uid];
      }
    }
    return \DJApi\API::OK(['R'=>$R]);
  }
  /**
   * 接口： msg/send_tpl
   * 发送模板消息
   * @request name: 公众号名称
   * @request openids: 接收用户的openid列表
   * @request tplName: 模板名称
   * @request data: 模板数据
   * @request url: 模板链接
   */
  public static function send_tpl($query){
    $name = $query['name'];
    if(!$name)return \DJApi\API::error(1001, "参数无效");
    $json = \DJApi\API::post(SERVER_API_ROOT, "user/wx_token/access_token", ['name'=>'请高手']);
    //\DJApi\API::debug(['获取access_token', $json]);
    $access_token = $json['datas']['access_token'];
    $openids = $query['openids'];
    $tplName = $query['tplName'];
    $data    = $query['data'];
    $url     = $query['url'];
    $R = _WxBaseFn::SendTPL($access_token, $openids, $tplName, $data, $url);
    \DJApi\API::debug(['发送。。。', $R]);
    return \DJApi\API::OK(['sended'=> count($openids), 'R'=>$R]);
  }

}


class _WxBaseFn{
  /**
   *  发送模板消息
   * @param access_token: 公众号的 access_token
   * @param openids: 要发送到的用户 openid 列表
   * @param tplName: 模板名
   * @param data: 模板消息的内容
   * @param url: 模板消息的链接
   * @return 已知的错误
   */
  static function SendTPL($access_token, $openids, $tplName, $data, $url){
    $tpl = \DJApi\Configs::get(["微信模板消息", $tplName]);
    if(!$tpl) return \DJApi\API::error(22, "模板参数错误, $tplName");
    if(!is_array($openids))$openids = [$openids];


    \DJApi\API::debug(['发送的openid', $openids]);

    $send_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$access_token";
    foreach($openids as $openid){
      if(strlen($openid)<10)continue;
      $D = [
        "touser"=> $openid,
        "template_id"=> $tpl["template_id"],
        "topcolor"=> "#FF8800",
        "url"=> $url,
        "data"=>[]
      ];
      $D["data"]['first' ] = ["value"=>$data[0], "color"=>$tpl["first" ]];
      $D["data"]['remark'] = ["value"=>$data[2], "color"=>$tpl["remark"]];
      foreach($tpl["body"] as $i => $item){
        $D["data"][$item[0]] = ["value"=>$data[1][$i], "color"=>$item[1]];
      }
      $json = urldecode(json_encode($D, JSON_UNESCAPED_UNICODE));
      $res = \DJApi\API::httpPost($send_url, $json);
      \DJApi\API::debug(['发送模板消息', 'json'=>$json, 'res'=>$res, 'data'=>$data]);
      $r[] = $res;
    }
    return \DJApi\API::OK(['tpl'=>$tpl, '发送返回'=>$r]);
    return API::OK('不知道发了几个');
  }

}

