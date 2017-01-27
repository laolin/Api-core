<?php


//111111111111111111111111111
class API{
  public static function json($data,$jsonp='',$disp=true) {
    if( $disp && !headers_sent() ) {
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
    if($disp)echo $str;
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
  public static function msg( $code=0, $msg='Ok.',&$param = null) {
    if ( $param == null ) {
      return [ 'errcode'=> $code , 'msg'=> $msg ];
    }
    $param['errcode']= $code;
    $param['msg']= $msg;
    return $param;
  }
  public static function data($data, $code=0, $msg='Ok.') {
    return ['data'=>$data, 'errcode'=> $code , 'msg'=> $msg ];
  }
  public static function is_error($msg)  {
    if(!isset($msg['errcode']))return -1;
    return $msg['errcode'];
  }
  public static function dump($v,$info=''){
    if(! SHOW_DEBUG_INFO ) return;
    echo "<hr/><h3>$info</h3><pre>";var_dump($v);echo '</pre>';
  }

  //获取数据库对象, 如果没有初始化则会自动初始化。
  public static function db() {
    $d=api_g('db');
    return $d ? $d : api_g('db', new laolinDb());
  }
  
  public static function GET($key) {
    return isset($_GET[$key])?trim($_GET[$key]):false;
  }
  public static function POST($key) {
    return isset($_POST[$key])?trim($_POST[$key]):false;
  }
  
  //比$_REQUEST更通用，当用 PUT 或  DELETE 或 跨域时 数据也能获取
  public static function INP($key) {
    if(isset($_GET[$key]) ) return trim($_GET[$key]);
    $ip=self::input();
    return isset($ip[$key])?trim($ip[$key]):false;
  }
  
  //非 GET 方式 （POST, PUT, DELETE方式）的数据获取
  public static function input() {
    $input=[];
    parse_str(file_get_contents('php://input'),$input);
    return $input;
  }
}
