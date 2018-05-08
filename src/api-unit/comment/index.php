<?php
/**
 * 评论模块
 *
 * 1.1 comment/li: 列出所有主贴
 * 1.2 comment/add: 添加一条主贴
 * 1.3 comment/remove: 删除一条主贴
 * 1.4 comment/feedback: 回复一条评论
 * 1.5 comment/removeFeedback: 删除一条回复
 * 1.6 comment/praise: 赞主贴
 * 1.7 comment/unpraise: 取消赞主贴
 *
 */
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 1);
ini_set("error_log", "php_errors.log");

require_once('apis/index.api-shell.php');


// 开启调试信息, 可以config中关闭
DJApi\API::enable_debug(true);
// 加载配置
search_require('configs/config.inc.comment.php');
//输出
apiShellCall('NApiUnit');
