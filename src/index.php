<?php
define ('API_G_KEY', 'api-wx-wp-cfg');
define ('API_G_VALUE_NEVER_USED', 'api-ww-cfgru123wq-0913QJ41qrjPOUU^&(*JKQWWAASDIQWU');
$GLOBALS[API_G_KEY]=[];

//定义全局函数api_g，方便使用。
//返回，或设置 全局参数值
function api_g( $key , $value= API_G_VALUE_NEVER_USED){
  if($value !== API_G_VALUE_NEVER_USED) {
    $GLOBALS[API_G_KEY][$key]=$value;
  }
  return isset($GLOBALS[API_G_KEY][$key])?$GLOBALS[API_G_KEY][$key]:'';
}

//真正源代码所在目录
api_g('path-src' , dirname( __FILE__ )  );

//真正运行web文件所在的目录，由于linux可以做符号链接，这里是符号链接所在的目录，而不是目标指向的目录
api_g('path-web', dirname ($_SERVER['SCRIPT_FILENAME']) ); 

//优先找path-web目录，方便替换配置文件
//然后找path-src目录
//再找不到就报错
if( file_exists(api_g('path-web').'/index.config.php' )) {
  require_once api_g('path-web').'/index.config.php';
} else if( file_exists(api_g('path-src').'/index.config.php' )) {
  require_once api_g('path-src').'/index.config.php';
} else {
  die('error config #1001');
}

date_default_timezone_set('Asia/Hong_Kong');
api_g('time',Date('Y-m-d H:i:s'));
api_g('YmdHis',Date('YmdHis'));

require api_g('path-src').'/include/include.all.php';

echo "<pre>Hello! @ " . time();
var_dump($GLOBALS[API_G_KEY]);


