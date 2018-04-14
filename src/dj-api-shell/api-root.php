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
spl_autoload_register(
  function ($class){
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
);


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
  static $readConfigOnceRecord = [];
  static function readConfigOnce($fileName = 'config.inc.php', $deep = 5, $path = ''){
    // 保证只引用一次，即使在多个目录中存在
    if(self::$readConfigOnceRecord[$fileName]) return;
    self::$readConfigOnceRecord[$fileName] = self::readConfig($fileName, $deep, $path, true);
  }
  static function readConfig($fileName = 'config.inc.php', $deep = 5, $path = '', $onlyonce = false){
    if(!$path){
      $path = dirname($_SERVER['PHP_SELF']);
    }
    $foundHere = file_exists("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
    // 如果找到了，而且只要求一次，那么就仅使用这一个了
    if($foundHere &&$onlyonce ){
      require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
      return true;
    }
    // 到上一级目录去找，有找到的先引用，然后再引用本级目录的(若有)，以保存越近的配置越优先
    if($deep > 0 && strlen($path) > 1){
      self::readConfig($fileName, $deep - 1, dirname($path), $onlyonce);
    }
    if($foundHere){
      require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
      return true;
    }
    return false;
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
  public static function now($ds = 0) {
    return date('Y-m-d H:i:s', time() + $ds);
  }
  public static function today($nday = 0) {
    return date('Y-m-d', time() + $nday * 3600 * 24);
  }

  /**
   * 随机串
   */
  static function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $bits = strlen($chars) - 1;
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, $bits), 1);
    }
    return $str;
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
    \DJApi\API::debug(['API::post()', 'url'=>$url, 'param'=>$param, '返回'=>$res]);
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
  static function is_https() {
    if ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
      return true;
    } elseif ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
      return true;
    } elseif ( !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
      return true;
    }
    return false;
  }
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
    // \DJApi\API::debug(['httpPost', 'info'=>curl_getinfo($ch), 'error'=>curl_error($ch), '返回'=>$output]);
    curl_close($ch);
    return $output;
  }
}

/**
 * 一些PHP版本兼容性函数
 */
class FN{

  static function array_column($arr, $k1, $k2 = false){
    if($k2 === true){
      $R = [];
      foreach($arr as $row){
        $R[$row[$k1]] = $row;
      };
      return $R;
    }
    if(function_exists("array_column")){
      return array_column($arr, $k1, $k2);
    }
    if($k2){
      $R = [];
      foreach($arr as $row){
        $R[$row[$k2]] = $row[$k1];
      };
      return $R;
    }
    else{
      $R = [];
      foreach($arr as $row){
        $R[] = $row[$k1];
      };
      return $R;
    }
  }
}

