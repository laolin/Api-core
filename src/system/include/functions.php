<?php


//111111111111111111111111111
class API{
  public static function json($data,$jsonp='') {
    if( !headers_sent() ) {
      if(strlen($jsonp) == 0 && isset($_REQUEST['callback']) && strlen($_REQUEST['callback'])>0)
        $jsonp=$_REQUEST['callback'];
      if(strlen($jsonp)>0)
        header('Content-type: application/javascript; charset=utf-8');
      else header('Content-type: application/json; charset=utf-8');
      header("Expires: Thu, 01 Jan 1970 00:00:01 GMT");
      header("Cache-Control: no-cache, must-revalidate");
      header("Pragma: no-cache");
    }
    $str='';
    if(strlen($jsonp)>0) {
      $str .= $jsonp.' ( ';
    }
    
    $str .=  json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    
    if(strlen($jsonp)>0) {
      $str .=  ' ); ';
    }
    echo $str;
    return $str;
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
   * @author Laolin 
  */
  public static function msg( $code, $msg='',&$param = null) {
    if ( $param == null ) {
      return [ 'err_code'=> $code , 'msg'=> $msg ];
    }
    $param['err_code']= $code;
    $param['msg']= $msg;
    return $param;
  }
  
  //获取数据库对象, 如果没有初始化则会自动初始化。
  public static function db() {
    $d=api_g('db');
    return $d || api_g('db', new laolinDb());
  }
  
  //非 GET 方式 （POST, PUT, DELETE方式）的数据获取
  public static function input() {
    $input=[];
    parse_str(file_get_contents('php://input'),$input);
    return $input;
  }
}
