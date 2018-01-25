<?php
namespace DJApi\LocalWx;

use DJApi\API;
use DJApi\Configs;
use DJApi\DB;

class class_msg{

  /**
   * 接口： msg/send_tpl
   * 发送模板消息
   * @request name: 公众号名称
   * @request openids: 接收用户的openid列表
   * @request tplName: 模板名称
   * @request data: 模板数据
   * @request url: 模板链接
   */
  public static function send_tpl($request){
    $name = $request->query['name'];
    if(!$name)return API::error(1001, "参数无效");
    $json = API::post(SERVER_API_ROOT, "server-wx/wx/access_token", ['name'=>'请高手']);
    $access_token = $json['datas']['access_token'];
    $openids = $request->query['openids'];
    $tplName = $request->query['tplName'];
    $data    = $request->query['data'];
    $url     = $request->query['url'];
    $R = self::SendTPL($access_token, $openids, $tplName, $data, $url);
    return API::OK(['sended'=> count($openids), 'R'=>$R]);
  }

  /**
   *  发送模板消息
   * @param access_token: 公众号的 access_token
   * @param openids: 要发送到的用户 openid 列表
   * @param tplName: 模板名
   * @param data: 模板消息的内容
   * @param url: 模板消息的链接
   * @return 已知的错误
   */
  private static function SendTPL($access_token, $openids, $tplName, $data, $url){
    //return API::error(22, "配置 = ", Configs::$values);
    $tpl = Configs::get(["微信模板消息", $tplName]);
    if(!$tpl) return API::error(22, "模板参数错误, $tplName", Configs::$values);
    if(!is_array($openids))$openids = [$openids];

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
      $json = urldecode(json_encode($D, JSON_UNESCAPED_UNICODE));//API::JSON_from($D);
      $res = API::httpPost($send_url, $json);
      //API::log_result('', "发送模板消息" . cn_json(['json'=>$json, 'res'=>$res, 'data'=>$data]));
      $r[] = $res;
    }
    return API::OK(['tpl'=>$tpl, '发送返回'=>$r, 'send_url'=>$send_url]);
    return API::OK('不知道发了几个');
  }
}

