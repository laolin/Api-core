<?php

class WX {
  
  static public function save_user($appid,$user_info,$uname){
    $prefix=api_g("api-table-prefix");
    $db=API::db();
    
    $r_old=$db->select($prefix.'user_wx',
      ['id','appFrom','uidBinded'],
      ['or'=>[
        'openid'=>$user_info['openid'],
        'unionid'=>$user_info['unionid'] ? $user_info['unionid'] : time()
        //注，由于有时可能 unionid 是空的，故写个 time 以让此条件不成立。
      ]]
      );
    $uidBinded=0;
    $indexOfData=-1;
    if($r_old) {
      api_g('$r_old',$r_old);
      $uidBinded=$r_old[0]['uidBinded'];//默认数据中不会发生绑定到多个UID的情况。
      for($i=count($r_old);$i--; ) {
        if($appid==$r_old[$i]['appFrom']) {
          $indexOfData=$i;
          break;
        }
      }
    }
    
    //1,  uidBinded=0, 需要到根用户表中创建一个新用户
    if(!$uidBinded) { // not binded to any uid, so create one
    
      //有可能以前绑定失败，故查找一下uname 是否存在
      $r_o2=$db->get($prefix.'user',['uid'],['uname'=>$uname]);
      if($r_o2) {//uname  存在
        $uidBinded=$r_o2['uid'];
      } else {//uname 不存在
        $r_ad=USER::__ADMIN_addUser($uname,//用户名： wx-xxx
          'ab:'.time()//随便指定一个不可登录的密码
        );
        if($r_ad['errcode']!=0) {
          return $r_ad;
        }
        $uidBinded=$r_ad['data'];
      }
    }
    $user_info['uidBinded']=$uidBinded;
      
    
    //2, else (indexOfData< 0), 未绑定
    //3, indexOfData >=0 , 已绑定
    
   if($indexOfData < 0){//2,未绑定，添加绑定
      $user_info['appFrom']=$appid;
      $r=$db->insert($prefix.'user_wx',
        $user_info);
      $id=$r;
    }
    
    
    //if($indexOfData >=0) {//3,已绑定，更新资料
    if(1) {//不管有没有绑定，由于存在多重绑定的可能，故都要更新资料
      unset($user_info['appFrom']);// appFrom 不能 全更新为统一数据
      unset($user_info['openid']);//  openid 不能 全更新为统一数据
      unset($user_info['unionid']);// unionid 不能全更新为统一数据
      unset($user_info['uidBinded']);//
      $r=$db->update($prefix.'user_wx',
        $user_info,
        // 所有 uidBinded 相等的都是同一用户，要全更新
        // 但 openid unionid 字段不能更新
        ['uidBinded'=>$uidBinded,'LIMIT'=>100]);
        
        // 用下面这个，则只会更新一行。
        // 但用户可能通过 web，公众号多途径登录，故会存在旧的用户资料
        //['id'=>$r_old[$indexOfData]['id'],'LIMIT'=>1]);
        
    } 
    api_g('$r_new',$r);
    return $uidBinded;//;

  }
  
  
  static public function update_user_by_uid( $uid ){
    $apps=api_g('WX_APPS');
    $appid=$apps['main'][0];
    $op=WX::getOpenIdByUid($appid,$uid);
    $uinfo=self::update_user_by_openid($appid,$op[0]['openid']);
    return $uinfo;
  }
  
  
  static public function update_user_by_openid($appid,$openid){
    $sys_mp_tk=WX::GetToken();
    $info_url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $sys_mp_tk . '&openid=' . $openid;
    $uif = json_decode(file_get_contents($info_url),true);

    // 字段，名字要和数据库要对应
    $user_info=[];
    
    $user_info['openid']=$uif['openid'];
    $user_info['subscribe']=$uif['subscribe'];

    $uif['subscribe_time']&&$user_info['subscribe_time']=$uif['subscribe_time'];
    $uif['groupid']&&$user_info['groupid']=$uif['groupid'];

    $uif['unionid']&&$user_info['unionid']=$uif['unionid'];
    $uif['nickname']&&$user_info['nickname']=$uif['nickname'];
    $uif['headimgurl']&&$user_info['headimgurl']=$uif['headimgurl'];
    $uif['sex']&&$user_info['sex']=$uif['sex'];
    $uif['language']&&$user_info['language']=$uif['language'];
    $uif['city']&&$user_info['city']=$uif['city'];
    $uif['province']&&$user_info['province']=$uif['province'];
    $uif['country']&&$user_info['country']=$uif['country'];
    
    $uname='wx-'.substr($user_info['openid'],-8);
    $uidBinded = WX::save_user($appid,$user_info,$uname);
    $user_info['uidBinded']=$uidBinded;
    return $user_info;
  
  } 
  
  public static function get_users( $ids ) {
    //if( ! USER::userVerify() ) {
    //  return API::msg(2001,'Error verify token.');
    //}
    if(!$ids) {
      return API::msg(3002,'No user.');
    }
    $users=explode(',',$ids);
    
    $d=USER::get_users( $users );
    
    if(API::is_error($d)) {
      return $d;
    }
    $arrIds=[];
    $idx=[];//保存id在 返回数据$d[data]的下标
    //为有效用户的uid
    for($i=count($d['data']);$i--; ) {
      $arrIds[$i]=$d['data'][$i]['uid'];
      $idx[$d['data'][$i]['uid']]=$i;
    }
    
    $db=API::db();
    
    $prefix=api_g("api-table-prefix");
    $r2=$db->select($prefix.'user_wx',
      ['subscribe','subscribe_time','uidBinded','nickname','sex','headimgurl'],
      ['uidBinded'=>$arrIds ]  );

    //根据 $idx 的索引，把wxinfo加到 $d[data] 中
    if(count($r2)) {
      for($i=0;$i<count($r2);$i++) {
        $d['data'][ $idx[$r2[$i]['uidBinded']] ]['wxinfo']=$r2[$i];
      }
    }
    
    
    return $d;
  }
  
  
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
  
  //返回是否关注
  static function isUserGz($uid,$appid='') {
    $prefix=api_g("api-table-prefix");
    $db=API::db();
    
    $apps=api_g('WX_APPS');
    if($appid=='')$appid=$apps['main'][0];
  
    $r=$db->select($prefix.'user_wx',
      ['uidBinded','openid','openid'
      ],
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
