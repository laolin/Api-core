<?php
//WWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWWW
/*
api: hello
method: get, post, put, delete
功能：就是测试用的，没有什么别的功能。
*/
class class_hello{
    public static function main($para1,$para2) {
      return self::world($para1,$para2);
    }
    public static function ip() {
      $res=API::msg(0,'Ok.');
      $res['ip']=$_SERVER['REMOTE_ADDR'];
      return $res;
    }
    public static function world($para1,$para2) {
      $method=$_SERVER['REQUEST_METHOD'];;
      $res=['Welcome'=> "Hello, world!",
        'My time'=>api_g('time')];
      $res['data_GET']=$_GET;
      $res['data_POST']=$_POST; 
      $res['data_for_PUT_or_DELETE_or_POST']=API::input(); 
      $res['data_method']=$method; 

      $res['para1']=$para1;
      $res['para2']=$para2;      
      $res['info']='GET OK @'.  api_g('time');
      return $res;
    }
}
