<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 0);
ini_set("error_log", "php_errors.log");

search_require('dj-api-shell/api-all.php');
// 请在正式的 index.php 中调用
//search_require('config.inc.php');


/**
 * 仅使用 api-shell 进行调用
 */
function apiShellCall($namespace = 'RequestByApiShell'){
  $request = new DJApi\Request($_GET['api'], $_GET['call']);
  $json = $request->getJson($namespace);
  DJApi\Response::response(DJApi\Request::debugJson($json));
}


/**
 * 自动识别
 * 尝试是否适合 api-shell，是则用之
 * 否则，用 LaolinApi 调用
 */
function autoAPI(){
  // 是否有 LaolinApi ?
  $hasLaolinApi = file_exists('system/main.php');
  if($hasLaolinApi)require_once 'system/main.php';
  /**
   * 优先使用 api-shell 执行
   */
  $request = new DJApi\Request($_GET['api'], $_GET['call']);
  $json = $request->getJson('RequestByApiShell');
  if($hasLaolinApi && in_array($json['errcode'], [DJApi\API::E_CLASS_NOT_EXITS, DJApi\API::E_API_NOT_EXITS])){
    // 调用旧的 API
    $json = main( );
    DJApi\API::debug('旧api，由 api-shell 拦截！');
    DJApi\Response::response(DJApi\Request::debugJson($json));
  }
  else{
    // 使用 api-shell
    DJApi\Response::response(DJApi\Request::debugJson($json));
  }
}




/**
 * 从当前文件夹的某个上级文件夹开始查找配置文件，并使用配置
 *
 * @param onlyonce:
 *     true : 找到一个最近的，即完成。
 *     false: 找到所有的匹配，从远处先使用，最近的将最优先。
 */
function search_require($fileName, $deep = 5, $path = '', $onlyonce = false){
  if(!$path){
    $path = dirname($_SERVER['PHP_SELF']);
  }
  $foundHere = file_exists("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
  // 如果找到了，而且只要求一次，那么就仅使用这一个了
  if($foundHere &&$onlyonce ){
    require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
    return;
  }
  // 到上一级目录去找，有找到的先引用，然后再引用本级目录的(若有)，以保存越近的配置越优先
  if($deep > 0 && strlen($path) > 1){
    search_require($fileName, $deep - 1, dirname($path), $onlyonce);
  }
  if($foundHere){
    require_once("{$_SERVER['DOCUMENT_ROOT']}$path/$fileName");
  }
}