<?php
namespace DJApi\LocalWx;

use DJApi\API;
use DJApi\Configs;
use DJApi\DB;

class class_wx{

  /**
   * 接口： wx/access_token
   * 获取 access_token
   * @request name: 公众号名称
   */
  public static function access_token($request){
    $name = $request->query['name'];
    if(!$name)return API::error(1001, "参数无效");
    return DjApi::post(SERVER_API_ROOT, "user/wx_token/access_token", ['name'=>$name]);
  }

}
