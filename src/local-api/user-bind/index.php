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

require_once('../index.api-shell.php');
// 加载配置
search_require('config.inc.php');
search_require('config.inc.use-record.php');
// 开启调试信息
DJApi\API::enable_debug(true);
//输出
apiShellCall('DJApi\UserBind');
