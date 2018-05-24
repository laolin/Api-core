<?php
/**
 * PHP 接口响应
 *
 * 支持 api 请求模式：
 * @模式 json  正常使用
 * @模式 jsonp 正常使用
 * @模式 cros 跨域
 *    默认允许全部域名
 *    可传递 $cros 参数以设定
 *
 * -------------- file: index.php ---------------

  require_once( "api-response.php");

  use DJApi\Response\Response;

  Response::response([
    "errcode" => 0,
    "datas" => [
      1,3,5, "BBB"
    ]
  ]);

 * ------------------------------------------
 */

namespace DJApi;

class Response {

  public static function response($json, $cros = "*") {
    if(isset($_REQUEST['callback']) && strlen($_REQUEST['callback'])>0){
      self:: jsonp($json);
    }
    else{
      self:: json($json, $cros);
    }
  }

  public static function json($json, $cros = "*") {
    $str = json_encode($json, JSON_UNESCAPED_UNICODE);
    if($cros){
      header("Access-Control-Allow-Origin:{$cros}");
    }
    header('Content-type: application/json; charset=utf-8');
    echo $str;
  }

  public static function jsonp($json) {
    $str = json_encode($json, JSON_UNESCAPED_UNICODE);
    header("Expires: Thu, 01 Jan 1970 00:00:01 GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header('Content-type: application/javascript; charset=utf-8');
    header("Pragma: no-cache");
    echo "{$_REQUEST['callback']}($str)";
  }

}

