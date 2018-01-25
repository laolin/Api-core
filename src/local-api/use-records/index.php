<?php
/**
 * 使用记录模块
 * 
 * 记录某个用户id使用某个功能的清单
 * 查询某条件下使用数量
 */
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 1);
ini_set("error_log", "php_errors.log");

define('API_SHELL' , 'dj-api-shell/api-all.php');
define('CONFIG_INC', 'config.inc.php');
define('CONFIG_MODULE', 'config.inc.use-record.php');

//echo json_encode([API_SHELL, CONFIG_INC]); return;

search_require(API_SHELL , 3, '', true); // 只需要一次
search_require(CONFIG_INC); // 请包含API-SHELL!允许多重配置，最近的覆盖远的
search_require(CONFIG_MODULE); // 请包含API-SHELL!允许多重配置，最近的覆盖远的
//echo "\n当前配置："; print_r(DJApi\Configs::$values);


$api   = isset($_GET['api'    ])?trim($_GET['api'    ]):'';//TODO: 这里需要验证合法文件名
$call  = isset($_GET['call'   ])?trim($_GET['call'   ]):'';//TODO: 这里需要验证合法函数名
$para1 = isset($_GET['__para1'])?trim($_GET['__para1']):'';
$para2 = isset($_GET['__para2'])?trim($_GET['__para2']):'';

$options = [
  "para1" => $para1,
  "para2" => $para2
];
$request = new DJApi\Request($api, $call, $options);
DJApi\Response::response($request->getJson());





/**
 * 从当前文件夹的某个上级文件夹开始查找配置文件，并使用配置
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
