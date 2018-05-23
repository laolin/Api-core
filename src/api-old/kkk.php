<?php
  
  error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
  ini_set("display_errors", 0);
  ini_set("error_log", "php_errors.log");

  require_once("../config/config.php");

  require_once("app/mymedoo.php");
  require_once("app/g.php");
  require_once("app/json.php");
  require_once("app/wx.php");

  require_once("api_wx.php");
  
  $db = new mymedoo();
  
  $userid = 212011;
  $openid = QgsModules::GetUser("openid", ["id"=>$userid]);

  //WX::send_payexpert_news($openid, 1, "回答");
  //WX::send_payexpert_news($openid, 1, "裁判");
  //WX::send_payexpert_news($openid, 1, "推广");
  //WX::send_payexpert_news($openid, 1, "支持");
  //
  //echo "发放酬金"; return;


  // 01
  // 02
  var_dump( class_wx::notice_toanswer($db, 1725,
     "当采用桌面电脑时，截屏如能直接进入上传图片则更方便用户了：）\nＢＴＷ：刚才“ＣＭ”新注册，仍然看不见。",
     $userid,
     $userid));
  
  
  // 03
  var_dump( class_wx::notice_passhalf($db, 1725, ["content"=>"当采用桌面电脑时，截屏如能直接进入上传图片则更方便用户了：）\nＢＴＷ：刚才“ＣＭ”新注册，仍然看不见。"]) );
  
  // 04
  var_dump( class_wx::notice_qaanswered($db, ["id"=>1725, "userid"=>$userid, "attr"=> ["content"=>"当采用桌面电脑时，截屏如能直接进入上传图片则更方便用户了：）\nＢＴＷ：刚才“ＣＭ”新注册，仍然看不见。"]]) );
  
  
  