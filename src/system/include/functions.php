<?php


//111111111111111111111111111

function echoRestfulData($data,$jsonp='') {
  if( !headers_sent() ) {
    if(strlen($jsonp) == 0 && isset($_REQUEST['jsonp']) && strlen($_REQUEST['jsonp'])>0)
      $jsonp=$_REQUEST['jsonp'];
    if(strlen($jsonp)>0)
      header('Content-type: application/javascript; charset=utf-8');
    else header('Content-type: application/json; charset=utf-8');
    header("Expires: Thu, 01 Jan 1970 00:00:01 GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
  }
  
  if(strlen($jsonp)>0) {
    echo $jsonp.' ( ';
  }
  
  echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  
  if(strlen($jsonp)>0) {
    echo ' ); ';
  }
}

/**
 * 返回错误信息数据的快捷函数
 *
 * @param int $code 错误代码，通常用0代表没有出错。
 *
 * @param string $msg 错误信息。
 *
 * @param object &$param 默认null，当指定此参数时，是个*引用传递*
 *   会把错误代码、错误信息直接写到此变量中。
 *
 * @return object 如果指定了第三个参数 $param ，则返回 $param 
 *    否则返回一个新的数组。
 *
 * @author Laolin 
*/
function e( $code, $msg='', &$param = null) {
  if ( $param == null ) {
    return [ 'err_code'=> $code , 'msg'=> $msg ];
  }
  $param['err_code']= $code;
  $param['msg']= $msg;
  return $param;
}




/**
 * 取得用户提交的数据的快捷函数
 *
 * 提取指定下标的用户输入的*$_GET*或*$_POST*或 *PUT* , *DELETE*提交来的数据
 * 并可避免*$key*不存在的notice警告
 *
 * @param string $key 指定的下标
 *
 * @param string $method 可选值*[get|post|put|delete|request]* 。
 *     默认request，使用$_REQUEST 。
 *     指定get或post时，分别使用 $_GET或$_POST 。
 *     指定put或delete时，使用file_get_contents('php://input')
 *       来获取用PUT或DELETE方式提资过来的数据。
 *
 * @return mix 返回下标$key对应的数据。下标不存在时返回false 。
 *
 * @author Laolin 
*/
function v( $key, $method='request' )
{
  $var = $method=='get' ? $_GET :
      $method=='post' ? $_POST :
      $_REQUEST;
  //    $method=='put' || $method=='delete' ? 0 : $_REQUEST;
  //if( $var === 0 ) parse_str(file_get_contents('php://input'),$var);//put || delete
  
  return isset( $var[$key] ) ? trim($var[$key]) : false;
}
