<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 1);
ini_set("error_log", "php_errors.log");

require_once( "dj-api-shell/api-all.php");

use DJApi\Response;
use DJApi\Request;
use DJApi\API;
use DJApi\Configs;

Configs::readConfig();


$api   = isset($_GET['api'    ])?trim($_GET['api'    ]):'';//TODO: 这里需要验证合法文件名
$call  = isset($_GET['call'   ])?trim($_GET['call'   ]):'';//TODO: 这里需要验证合法函数名
$para1 = isset($_GET['__para1'])?trim($_GET['__para1']):'';
$para2 = isset($_GET['__para2'])?trim($_GET['__para2']):'';

$options = [
  "para1" => $para1,
  "para2" => $para2
];
$request = new Request($api, $call, $options);

Response::response($request->getJson());

