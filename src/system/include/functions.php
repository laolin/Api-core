<?php


//111111111111111111111111111
class API{
  public static function json($data,$jsonp='') {
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
   * @author Laolin 
  */
  public static function msg( $code, $msg='',$jsonp='') {
      return API::json( [ 'err_code'=> $code , 'msg'=> $msg ],$jsonp);
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
