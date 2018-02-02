<?php
/**
 * PHP 接口通用定义
 *
 */

namespace DJApi;
use DJApi;

/**
 * 自动加载自己的类库
 * @类库基目录: 从 index.php 所在目录开始计算
 *   默认：['classes', 'apis/classes', 'system/include']
 *   自定义：
 *     DJApi\Configs::set('main-include-path', [include_path1, ...])
 */
class AutoClass {
  static function classLoader($class){
    $fn = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $paths = DJApi\Configs::get('main-include-path');
    if(!$paths){
      $paths = ['classes', 'apis/classes', 'system/include'];
    }
    if(is_string($paths)) {
      $paths = [$paths];
    }
    foreach($paths as $path){
      $fullFilename = dirname($_SERVER['SCRIPT_FILENAME']) . "/$path/$fn";
      // error_log("查找类名： {$fullFilename}");
      if(file_exists($fullFilename)){
        require_once $fullFilename;
      }
    }
  }
}
spl_autoload_register('DJApi\AutoClass::classLoader');


class Configs {
  static $values = [];

  /**
   * 设置一个配置值
   */
  static function set($keys, $value){
    if(!is_array($keys)){
      $keys = [$keys];
    }
    $length = count($keys);
    $arr = &self::$values;
    for($i =0; $i < $length - 1; $i ++){
      $k = $keys[$i];
      if(!is_array($arr[$k])) $arr[$k] = [];
      $arr = &$arr[$k];
    }
    $arr[$keys[$length - 1]] = $value;
  }

  /**
   * 读取一个设置值
   */
  static function get($keys){
    if(!is_array($keys)){
      $keys = [$keys];
    }
    $arr = self::$values;
    foreach($keys as $k){
      if(!isset($arr[$k])) return "";
      $arr = $arr[$k];
    }
    return $arr;
  }

  /**
   * 从当前文件夹的某个上级文件夹开始查找配置文件，并使用配置
   */
  static function readConfig($fileName = 'config.inc.php', $deep = 5, $path = ''){
    if(!$path){
      $path = dirname($_SERVER['PHP_SELF']);
    }
    if($deep > 0 && strlen($path) > 1){
      self::readConfig($fileName, $deep - 1, dirname($path));
    }
    if(file_exists("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName")){
      require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
    }
  }
}

class API{
  const E_NEED_LOGIN         = 1;  // 未登录
  const E_NEED_RIGHT         = 2;  // 权限不足
  const E_API_NOT_EXITS      = 101;
  const E_CLASS_NOT_EXITS    = 102;
  const E_FUNCTION_NOT_EXITS = 103;
  const E_PARAM_ERROR        = 201; // 参数错误


  static $debug;
  static $enable_debug = false;
  static function enable_debug($b){
    self::$enable_debug = $b;
  }
  static function debug($value, $k = ''){
    if($k){
      self::$debug[$k] = $value;
    }
    else{
      self::$debug[] = $value;
    }
  }


  static function cn_json($arr){
    return json_encode($arr, JSON_UNESCAPED_UNICODE);
  }

  static function toJson($res) {
    if(is_array($res)){
      $json = ['errcode'=>$res[0]];
      if($res[0]){
        $json['errmsg'] = $res[1];
        if(isset($res[2]) && $res[2] !== false)$json['datas' ] = $res[2];
      }
      else{
        $json['datas'] = $res[1];
      }
      return $json;
    }
    return json_decode($res, true);
  }

  public static function error($code, $msg='error', $additional_datas = false) {
    return self::toJson([$code, $msg, $additional_datas]);
  }

  public static function OK($datas = []) {
    return self::toJson([0, $datas]);
  }

  public static function isOk($json) {
    return $json && 0 + $json['errcode'] === 0;
  }

  /**
   * 两个旧版函数
   */
  public static function msg($code, $msg='error', $additional_datas = false) {
    return self::error($code, $msg, $additional_datas);
  }
  public static function datas($datas = []) {
    return self::OK([0, $datas]);
  }


  /**
   * 时间函数
   */
  public static function now() {
    return date('Y-m-d H:i:s');
  }
  public static function today($nday = 0) {
    return date('Y-m-d', time() + $nday * 3600 * 24);
  }


  /**
   * 直接调用本地 API 函数
   * 方式：函数调用(暂时模拟，仅提供接口，以后再实现)
   */
  static function call($module, $api, $param) {
    return self::post($module, $api, $param);
  }

  /**
   * 向其它模块发出请求
   * 方式：curl, post
   */
  static function post($module, $api, $param) {
    $url = $module . $api;
    $res = self::httpPost($url, $param);
    return self::toJson($res);
  }

  /**
   * 向其它模块发出请求
   * 方式：curl, get
   */
  static function get($url) {
    $res = self::httpGet($url, $param);
    return self::toJson($res);
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃             后台网页请求                                               ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);
    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
  }

  static function httpPost($url, $param) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt ($ch, CURLOPT_REFERER, "http://pgy");
    curl_setopt($ch, CURLOPT_POST, 1);
    if(is_array($param) && count($param)>0){
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
    }
    else if(is_string($param)){
      curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    }
    else{
      curl_setopt($ch, CURLOPT_POSTFIELDS, []);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //信任任何证书
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 检查证书中是否设置域名,0不验证
    $output = curl_exec($ch);
    //$info = curl_getinfo($ch);
    //error_log(curl_error($ch));
    curl_close($ch);
    //if($output===false)return curl_error($ch);
    return $output;
  }
}

