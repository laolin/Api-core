<?php
/**
 * PHP 接口请求层
 *
 * ------- file: index.php --------
  require_once( "api-request.php");
  use DJApi\Request\Request;
  use DJApi\API;

  $api   = isset($_GET['api'    ])?trim($_GET['api'    ]):'';//TODO: 这里需要验证合法文件名
  $call  = isset($_GET['call'   ])?trim($_GET['call'   ]):'';//TODO: 这里需要验证合法函数名
  $para1 = isset($_GET['__para1'])?trim($_GET['__para1']):'';
  $para2 = isset($_GET['__para2'])?trim($_GET['__para2']):'';

  $options = [
    "para1" => $para1,
    "para2" => $para2
  ];
  $request = new Request($api, $call, $options);

  echo DJApi\API::cn_json($request->getJson());

 */

namespace DJApi;
use DJApi;
require_once( "api-root.php");


class Request {
  /**
   * 构造函数，保存参数
   */
  function __construct($api, $call, $options = []) {
    $this->api = $api;
    $this->call  = $call;
    $this->para1 = $options['para1'];
    $this->para1 = $options['para1'];
    $this->query = $_REQUEST;
    if(!$this->call ) $this->call  = 'main';
    if(!$this->para1) $this->para1 = '';
    if(!$this->para1) $this->para1 = '';
  }

  /**
   * 执行 api 调用，并返回 json 数据
   */
  function getJson($namespace){
    $apiFile = $this->getApiFile();
    if(!$apiFile){
      return DJApi\API::error(DJApi\API::E_API_NOT_EXITS, '请求错误');
    }

    require_once($apiFile);
    $C = ($namespace? "$namespace\\": "") ."class_{$this->api}";
    $CALL="{$this->call}";
    if(! class_exists($C) ) {
      return DJApi\API::error(DJApi\API::E_CLASS_NOT_EXITS, '请求错误', [$this, $apiFile, $C]);
    }

    if( method_exists($C, 'API') ) {
      // 调用共享 API 函数
      return $C::API($this->call, $this);
    }
    else if(! method_exists($C,$CALL) ) {
      return DJApi\API::error(DJApi\API::E_FUNCTION_NOT_EXITS, '请求错误');
    }
    return $C::$CALL($this);
  }

  /**
   * 增加调试数据到 json 数据
   */
  static function debugJson($json){
    if(DJApi\API::$enable_debug && DJApi\API::$debug){
      if($json['__debug__']){
        $json['__debug__'] = [
          "old" => $json['__debug__'],
          "New" => DJApi\API::$debug
        ];
      }
      else{
        $json['__debug__'] = DJApi\API::$debug;
      }
    }
    return $json;
  }

  /**
   * （内部函数）
   * 根据构造的参数，获取需要调用的文件名
   */
  protected function getApiFile(){
    $paths = DJApi\Configs::get('main-api-path');
    if(!$paths){
      $paths = ['apis', 'apis/apis-user', 'apis/apis-core'];
    }
    if(is_string($paths)) {
      $paths = [$paths];
    }
    foreach($paths as $path){
      $apiFile = dirname($_SERVER['SCRIPT_FILENAME']) . "/$path/api_{$this->api}.php";
      if(file_exists($apiFile)){
        return $apiFile;
      }
    }
    return false;
  }

  /**
   * 安全的请求参数
   */
  function safeQuery($name, $defaultValue = ''){
    if(!isset($this->query[$name]))return $defaultValue;
    return $this->query[$name];
  }
}

