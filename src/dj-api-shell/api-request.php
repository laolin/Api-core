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

  echo API::cn_json($request->getJson());

 */

namespace DJApi;

require_once( "api-root.php");
use DJApi\API;


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
      return API::error(API::E_API_NOT_EXITS, '请求错误');
    }

    require_once($apiFile);
    $C = ($namespace? "$namespace\\": "") ."class_{$this->api}";
    $CALL="{$this->call}";
    if(! class_exists($C) ) {
      return API::error(API::E_CLASS_NOT_EXITS, '请求错误', [$this, $apiFile, $C]);
    }

    if( method_exists($C, 'API') ) {
      // 调用共享 API 函数
      return $C::API($this->call, $this, $para1, $para2);
    }
    else if(! method_exists($C,$CALL) ) {
      return API::error(API::E_FUNCTION_NOT_EXITS, '请求错误');
    }
    return $C::$CALL($this, $para1, $para2);
  }

  /**
   * （内部函数）
   * 根据构造的参数，获取需要调用的文件名
   */
  protected function getApiFile(){
    $apiFile = dirname($_SERVER['SCRIPT_FILENAME']) . "/apis/api_{$this->api}.php";
    if(file_exists($apiFile)){
      return $apiFile;
    }
    return false;
  }
}

