<?php
  define('SHOW_DEBUG_INFO',1);

require_once 'system/main.php';

/*
每个api对应一个/apis/目录下的一个文件
RewriteRule ^([a-zA-Z]\w+)/([a-zA-Z]\w+)$ index.php?api=$1&call=$2&%{QUERY_STRING}	[L]

要求：
文件名："/api_$api.php"
类名："class_$api"
函数：public static function $call() {}
*/




main( );
