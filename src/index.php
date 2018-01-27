<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 0);
ini_set("error_log", "php_errors.log");

require_once 'dj-api-shell/api-all.php';
DjApi\Configs::readConfig('config.inc.main.php');

require_once 'system/main.php';

/**
 * 优先使用 api-shell 执行
 */
$request = new DJApi\Request($_GET['api'], $_GET['call']);
$json = $request->getJson('RequestByApiShell');
if($json['errcode'] == DJApi\API::E_CLASS_NOT_EXITS || $json['errcode'] == DJApi\API::E_API_NOT_EXITS){
  /* see README.md */
  main( );
}
else{
  echo DJApi\API::cn_json($json);
}
