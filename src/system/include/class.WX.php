<?php

class WX {
  static function GetToken() {
    //注意
    //目前依靠从下面文件中的函数 WxToken::xxx() 
    // 获取全局 access_token
    if(!file_exists( api_g('path-web') . '/WxToken.inc.php'))
      return API::msg(601,'Error missing file : WxToken.inc');
    require_once api_g('path-web') . '/WxToken.inc.php';
    $tok=WxToken::GetToken();
    return $tok;
  }
  static function getOpenIdByUid($appid,$uidArr) {
    $prefix=api_g("api-table-prefix");
    $db=API::db();
    
    $r=$db->select($prefix.'user_wx',
      ['uidBinded','openid'],
      [
        'and'=>[ 'uidBinded'=>$uidArr, 'appFrom'=>$appid ],
        'LIMIT'=>999
      ]
    );
    return $r;
  }
  
//======================================================
	/**
	 *  S1 客服接口-发消息
	 */
   
   
/*
	接口调用请求说明

	http请求方式: POST
	https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=ACCESS_TOKEN
	各消息类型所需的JSON数据包如下：

	发送文本消息

	{
		"touser":"OPENID",
		"msgtype":"text",
		"text":
		{
			 "content":"Hello World"
		}
	}
	发送图片消息

	{
		"touser":"OPENID",
		"msgtype":"image",
		"image":
		{
		  "media_id":"MEDIA_ID"
		}
	}
	发送语音消息

	{
		"touser":"OPENID",
		"msgtype":"voice",
		"voice":
		{
		  "media_id":"MEDIA_ID"
		}
	}
	发送视频消息

	{
		"touser":"OPENID",
		"msgtype":"video",
		"video":
		{
		  "media_id":"MEDIA_ID",
		  "thumb_media_id":"MEDIA_ID",
		  "title":"TITLE",
		  "description":"DESCRIPTION"
		}
	}
	发送音乐消息

	{
		"touser":"OPENID",
		"msgtype":"music",
		"music":
		{
		  "title":"MUSIC_TITLE",
		  "description":"MUSIC_DESCRIPTION",
		  "musicurl":"MUSIC_URL",
		  "hqmusicurl":"HQ_MUSIC_URL",
		  "thumb_media_id":"THUMB_MEDIA_ID" 
		}
	}
	发送图文消息 图文消息条数限制在10条以内，注意，如果图文数超过10，则将会无响应。

	{
		"touser":"OPENID",
		"msgtype":"news",
		"news":{
			"articles": [
			 {
				 "title":"Happy Day",
				 "description":"Is Really A Happy Day",
				 "url":"URL",
				 "picurl":"PIC_URL"
			 },
			 {
				 "title":"Happy Day",
				 "description":"Is Really A Happy Day",
				 "url":"URL",
				 "picurl":"PIC_URL"
			 }
			 ]
		}
	}
*/
	

  // 客服接口，文本消息，48小时内无互动会发送失败
  static function send_service_message_text($openid, $text){
    $msg = [ "touser"=> $openid , "msgtype"=>"text", 
      "text"=> [ "content"=>$text]
    ];
    $ret = self::send_service_message($msg);
    return $ret;
  }

  // 客服接口，图文消息，48小时内无互动会发送失败
  // TODO: 多条图文
  static function send_service_message_news($openid, $title, $description, $newsurl, $picurl){
    
    $msg =  ["touser"=>$openid,"msgtype"=>"news", "news"=>["articles"=>[
      ["title"=>$title,
       "description"=>$description,
       "url"=>$newsurl,
       "picurl"=>$picurl]
    ]]];

    return self::send_service_message($pmsgaram);
  }


  // ============================================================= 
  static function send_service_message($msgObj){
    //客服接口，48小时内无互动会发送失败。
    $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . self::GetToken();
    $param = json_encode($msgObj, JSON_UNESCAPED_UNICODE);
    $ret= G::post($url, $param);
    $r2 = json_decode($ret, true);
    if(!isset($r2["errcode"]))
      return API::msg(2001,'Error');
    if($r2["errcode"] != "0")
      return API::msg(2002,"Err:".$r2["errcode"].':'.$r2["errmsg"]);
    return API::data(1);//发送成功
  }


  /*
  {
     "touser":"OPENID",
     "template_id":"ngqIpbwh8bUfcSsECmogfXcV14J0tQlEpBO27izEYtY",
     "url":"http://weixin.qq.com/download",  
     "miniprogram":{
       "appid":"xiaochengxuappid12345",
       "pagepath":"index?foo=bar"
     },          
     "data":{
             "first": {
                 "value":"恭喜你购买成功！",
                 "color":"#173177"
             },
             "keynote1":{
                 "value":"巧克力",
                 "color":"#173177"
             },
             "keynote2": {
                 "value":"39.8元",
                 "color":"#173177"
             },
             "keynote3": {
                 "value":"2014年9月22日",
                 "color":"#173177"
             },
             "remark":{
                 "value":"欢迎再次购买！",
                 "color":"#173177"
             }
     }
  }
  
  */
  
	/**
	 *  S2 模板消息
	 */
   
  static function send_tpl_message($D) {
    $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . self::GetToken();
    
    $json= urldecode(json_encode($D,JSON_UNESCAPED_UNICODE));
    $ret = G::post($url,$json );
    $r2 = json_decode($ret, true);
    if(!isset($r2["errcode"]))
      return API::msg(2001,'Error');
    if($r2["errcode"] != "0")
      return API::msg(2002,"Err:".$r2["errcode"].':'.$r2["errmsg"]);
    return API::data(1);//发送成功

  }   
}
