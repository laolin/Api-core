<?php
define ('API_G_KEY', 'api-wx-wp-cfg');
define ('API_G_VALUE_NEVER_USED', 'api-ww-cfgru123wq-0913QJ41qrjPOUU^&(*JKQWWAASDIQWU');


$GLOBALS[API_G_KEY]=[];
date_default_timezone_set('Asia/Hong_Kong');

if( isset($_GET['debug']) )
  define('SHOW_DEBUG_INFO',1);
else
  define('SHOW_DEBUG_INFO',0);



//定义全局函数api_g，方便使用。
//返回，或设置 全局参数值
function api_g( $key , $value= API_G_VALUE_NEVER_USED){
  if($value !== API_G_VALUE_NEVER_USED) {
    $GLOBALS[API_G_KEY][$key]=$value;
  }
  return isset($GLOBALS[API_G_KEY][$key])?$GLOBALS[API_G_KEY][$key]:'';
}

//真正源代码所在目录
api_g('path-sys' , dirname( __FILE__ )  );


//真正源代码所在目录
api_g('path-api' , dirname( dirname( __FILE__ ) ) . '/apis'  );

//真正运行web文件所在的目录，由于linux可以做符号链接，这里是符号链接所在的目录，而不是目标指向的目录
api_g('path-web', dirname ($_SERVER['SCRIPT_FILENAME']) ); 

// 这里不再按原来的方法来找配置了
// 包含此文件后，就固定上朔查找 configs/config.inc.laolin.php 这个文件

require_once api_g('path-sys').'/include/include.all.php';


function main() {
  api_g('time',Date('Y-m-d H:i:s'));
  api_g('YmdHis',Date('YmdHis'));
  
  
  $api=isset($_GET['api'])?trim($_GET['api']):'';
  $call=isset($_GET['call'])?trim($_GET['call']):'';
  $para1=isset($_GET['__para1'])?trim($_GET['__para1']):'';
  $para2=isset($_GET['__para2'])?trim($_GET['__para2']):'';
  $r=api_core__runapi($api,$call,$para1,$para2);

  return $r;
  if($r)return API::json($r);
}

//此函数不 调用 API::json，不会显示内容给客户端。
function api_core__runapi($api,$call,$para1,$para2) {
  $data=[];
  
  
  api_g('api',"/$api/$call/$para1/$para2");
  
  
  if($api==''  || ! preg_match('/^[a-zA-Z_][a-zA-Z\d_]{0,127}$/i', $api) ){
    return API::msg(1002,"Please specify a valid API.");
  }
  if($call==''){
    $call='main';//默认函数名
  }
  if(! preg_match('/^[a-zA-Z_][a-zA-Z\d_]{0,127}$/i', $call)){
    return API::msg(1003,"Please specify a valid method.");
  }

  // 查找文件
  $path_apis = DJApi\Configs::get("api-path");
  $path_apis_root = dirname( dirname( __FILE__ ) );
  $api_file = false;
  foreach($path_apis as $path){
    $fn = "$path_apis_root/$path/api_{$api}.php";
    if(file_exists($fn)){
      $api_file = $fn;
      require_once $fn;
    }
  }
  if(! $api_file) {
    DJApi\API::debug(['查找文件失败', $path_apis, $path_apis_root]);
    return API::msg(1101, "Error load api:$api");//再找不到就出错。
  }
  api_g('DBG_api-file',$api_file);

  //prepare db 
  $db=API::db();
  $tbl_prefix=api_g("api-table-prefix");
  //由于旧版没有这个设置，故要做个默认值
  if(!$tbl_prefix)$tbl_prefix='api_tbl_';

  
  //uid for api-log, bucketUser for TokenBucket
  $bucketUser=$uid = \MyClass\CUser::sign2uid();
  $bucketSetting=api_g('api_bucket');
  $api_cost=1;
  if($uid && ! USER::userVerify() ) {
    $uid=0;
  }
  if(!$uid) {
    $bucketUser=$_SERVER['REMOTE_ADDR'];
    $api_cost=$bucketSetting['anony_cost'];
  }
  
  //TokenBucket
	$tbData = $db->get($tbl_prefix.'tokenbucket',
    ['id', 'capacity',	'tokens',	'fillRate',	'lastRun'],
    ['and'=>['user'=>$bucketUser]]
    );
  if(!$tbData) {
    $tbData=['capacity'=>$bucketSetting['capacity'],
      'tokens'=>$bucketSetting['capacity'],
      'fillRate'=>$bucketSetting['fillrate'],
      'lastRun'=>0];
  }
    
  $bucket = new TokenBucket($tbData);
  
  // 有uid,每次消费1个令牌. 没有uid,每次消费多个令牌
	if(!$bucket->consume($api_cost)) {
    return API::msg(1002,"Too many calls, please wait some seconds.");
  }
  $tbData_new=$bucket->data();

  if( isset($tbData['id'])) {
    $tbData = $db->update($tbl_prefix.'tokenbucket',
      $tbData_new,
      ['and'=>['id'=>$tbData['id'] ] ]
      );
  } else {
    $tbData_new['user']=$bucketUser;
    $tbData = $db->insert($tbl_prefix.'tokenbucket',$tbData_new);
  }
  


  //log api 
  $tbl_log=$tbl_prefix.'log';
  $require_id = $db->insert($tbl_log, 
    ["uid"=>$uid,
    "api"=>"/$api/$call/$para1/$para2",
    "host"=>$_SERVER['REMOTE_ADDR'], 
    "cur_time"=>time(),
    "get"=>json_encode($_GET, JSON_UNESCAPED_UNICODE), 
    "post"=>json_encode(API::input(), JSON_UNESCAPED_UNICODE)]);


  require_once $api_file;
  $C="class_$api";
  if(! class_exists($C) ) {
    return API::msg(1102,"class not exists $C");
  }
  if(! method_exists($C,$call) ) {
    return API::msg(1103,"method not exists $C.$call");
  }
  $data=$C::$call($para1,$para2);
  if(SHOW_DEBUG_INFO){
    api_g("DBUSER",'***');
    api_g("DBPASS",'***');
    api_g("WX_APPSEC",'***');
    api_g("WX_APPS",'***');
    $data[API_G_KEY]=$GLOBALS[API_G_KEY];
  }

  if($data === false ) return false;
  return $data;
}

