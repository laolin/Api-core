<?php
  error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
  ini_set("display_errors", 0);
  ini_set("error_log", "php_errors.log");
  
  require_once("../dj-api-shell/api-all.php");

  require_once("../../config/config.php");
  require_once("app/mymedoo.php");
  require_once("app/g.php");
  require_once("app/json.php");
  require_once("app/wx.php");

  $api = trim( $_GET['api'] ); unset($_GET['api']);
  $CALL = trim( $_GET['call'] );unset($_GET['call']);
  if(strtolower($_SERVER['REQUEST_METHOD']) == 'post')$query = &$_POST;
  else $query = &$_GET;
  if(!$query)$query = [];
  $uid = isset($query['uid'])?$query['uid']:0;

  $now = G::s_now();
  $_API_t_ = time();
  function API_now($ss = 0){return date("Y-m-d H:i:s", $GLOBALS['_API_t_'] + $ss);}
  $db = new mymedoo();
  require_once("api_timer.php");
  class_timer::run_timer($db, 5);//每个api调用都却测试定时器，间隔时间要大于专用定时器的间隔的2倍，以保证API调用的速度
  $require_id = $db->insert("log_api", ["rq"=>$now,"api"=>$api,"call"=>$CALL,"userid"=>$uid, "host"=>$_SERVER['REMOTE_ADDR'], "query"=>cn_json($query)]);

  header("Access-Control-Allow-Origin:*");

  switch($api){
    case "pay":
    case "wxpay":
      require_once("api_wxpay.php");
      $result = class_wxpay::$CALL($_GET, $_POST, $db);
      echo $result;
      $db->update("log_api", ["result"=>$result], ["id"=>$require_id]);
      return;
    case "wx":
    case "qgs":
    case "qa":
    case "bbs":
    case "kl":
    case "user":
    case "newyear2016":
    case "search":
    case "fileupload":
    case "qrcode":
      require_once("api_$api.php");
      $C = "class_$api";
      $result =  $C::$CALL($_GET, $_POST, $db);
      
      echo $result;
      $db->update("log_api", ["result"=>$result], ["id"=>$require_id]);
      return;
    default:
      if(!file_exists("api_$api.php")){ echo '{"errcode":-1,"errmsg":"非法操作"}'; return;}
      require_once("api_$api.php");
      $C = "class_$api";
      $C::$CALL($_GET, $_POST, $db);
      return;
  }
?>