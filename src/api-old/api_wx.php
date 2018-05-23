<?php
	require_once("app/user.class.php");
	require_once("app/g.php");
	require_once("app/wx.php");
	require_once("api_qrcode.php");

class class_wx{
	static function wx_url($url){
		return WX_DOMAIN . "/qgs/wx?hash=".urlencode($url);
	}
	static function static_url($url){
		return WX_DOMAIN . ($url);
	}
	
  /* -----------------------------------------------------
   * 请求微信JSAPI接口参数
   * ----------------------------------------------------- */
  public static function getwxconfig(&$get, &$post, &$db){
    $query = &$get;
    $jsapiTicket = WX::GetJsApiTicket();

    $url = $query['url'];

    $timestamp = time();
    $nonceStr = G::createNoncestr(16);

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
    $signature = sha1($string);

    return json_OK(["config"=>[
        "appId"     => APPID,
        "nonceStr"  => $nonceStr,
        "timestamp" => $timestamp,
        "signature" => $signature
      ]]);
  }
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 同步公众号文章列表到本地数据库
	 * xxx.com/wx/syncnewslist (GET )
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function syncnewslist(&$get, &$post, &$db){
		$query = &$get;
		$me = ASSERT_query_userinfo($db, $query, []);
    ASSERT_right($db, $me, "推送消息");
		//同步公众号文章列表到本地数据库
		WX::sync_news($db);
	  return json_OK([]);
	}
	/* -----------------------------------------------------------------------------------
	 * 从本地数据库获取文章列表
	 * ----------------------------------------------------------------------------------- */
	public static function getnewslist(&$get, &$post, &$db){
		$query = &$post;
		$me = ASSERT_query_userinfo($db, $query, ["en", "page", "pagesize"]);
    ASSERT_right($db, $me, "推送消息");

    //分析页码
    $uid = trim($query['uid']);
    $page = $query['page'] - 1; if($page < 0)$page = 0;
    $pagesize = $query['pagesize'] + 0; if($pagesize < 1)$pagesize = 1;
    $first = $pagesize * $page;

    $R = $db->select("wx_news", "*", [ "LIMIT"=>[$first, $pagesize], "ORDER"=>"update_time DESC"] );
    $rowcount = $db->count("wx_news", "*");
    
    return json_OK(["list"=>$R, "rowcount"=>$rowcount]);
	}
  /* -----------------------------------------------------
   * 验证给多个用户 openid 列表
   * ----------------------------------------------------- */
  static function ASSERT_send_openids(&$togroup, &$db){
    if(count($togroup)>1)json_error_exit(2000, "选择群组后，只允许使用接口发送。");
    $userlist = &$togroup['userlist'];
    if(!isset($userlist) || !is_array($userlist) || count($userlist)<1)json_error_exit(2000, "未指定用户1。", ["userlist"=>$userlist]);
    $openids = QgsModules::SelectUser(["id","openid"], ["AND"=>["id"=>$userlist]]);
    if(!is_array($openids) || count($openids) < 1)json_error_exit(2000, "未指定用户2。");
    return $openids;
  }
  /* -----------------------------------------------------
   * 遍历推送 - 文本
   * ----------------------------------------------------- */
  public static function sendtext(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["togroup", "text"]);
    ASSERT_right($db, $me, "推送消息");
  	$openids = self::ASSERT_send_openids($query['togroup'], $db);

    $text = trim($query['text']);

    $success = [];
    foreach($openids as $user){
      $json = json_decode( WX::send_service_text($user['openid'], $text), true);
    	if($json['errcode'] == 0)$success[] = $user['id'];
    	else $failed[] = $user['id'];
    }
    return json_OK(["success"=>$success, "failed"=>$failed]);
  }
  /* -----------------------------------------------------
   * 遍历推送 - 图文消息
   * ----------------------------------------------------- */
  public static function sendnews(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["togroup", "newsid"]);
    ASSERT_right($db, $me, "推送消息");
  	$openids = self::ASSERT_send_openids($query['togroup'], $db);

    $newsid = $query["newsid"] + 0;
    $news = $db->get("wx_news", "*", ["id"=>$newsid]);
    if(!is_array($news)) return json_error(2000, "消息ID错误");

    $success = [];
    $failed = [];
    foreach($openids as $user){
      $json = json_decode(WX::json_send_service_news($user['openid'], $news['title'], $news['digest'], $news['url'], self::static_url($news['pic_url'])), true);
    	if($json['errcode'] == 0)$success[] = $user['id'];
    	else $failed[] = $user['id'];
    }
    return json_OK(["success"=>$success, "failed"=>$failed]);
  }

  /* -----------------------------------------------------
   * 验证群发 openids
   * ----------------------------------------------------- */
  static function ASSERT_group_openids(&$togroup, &$db){
    if(!is_array($togroup) || count($togroup) < 1)json_error_exit(2001, "未指定用户。");
    $OR = [];
    if(isset($togroup['user']) && $togroup['user']) $OR["userstate[>]"]=2;
    if(isset($togroup['expert']) && $togroup['expert']) $OR["expertstate[>]"]=2;
    if(isset($togroup['service']) && $togroup['service']) $OR["servicestate[>]"]=2;
    if(isset($togroup['guest']) && $togroup['guest']) $OR["AND"]=["userstate[<]"=>3, "expertstate[<]"=>3, "servicestate[<]"=>3];
    if(isset($togroup['userlist']) && $togroup['userlist']) $OR["id"]=$togroup['userlist'];

    $openids = QgsModules::SelectUser("openid", ["OR"=>$OR]);
    if(!is_array($openids) || count($openids) < 1)json_error_exit(2002, "未指定用户。");
    return $openids;
  }
  /* -----------------------------------------------------
   * 群发 - 文本
   * ----------------------------------------------------- */
  public static function sendtextbyapi(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["togroup", "text"]);
    ASSERT_right($db, $me, "推送消息");
  	$openids = self::ASSERT_group_openids($query['togroup'], $db);

    $text = trim($query['text']);

    $json = json_decode( WX::send_sendall_text($openids, $text), true);
    if($json['errcode'] != 0)return json_error(2000, "推送群发文本消息失败", ["A"=>$json, "openids"=>$openids]); //发送失败

    return json_OK([]);
  }
  /* -----------------------------------------------------
   * 群发 - 图文消息
   * ----------------------------------------------------- */
  public static function sendnewsbyapi(&$get, &$post, &$db){
    $query = &$post;
    $me = ASSERT_query_userinfo($db, $query, ["togroup", "newsid"]);
    ASSERT_right($db, $me, "推送消息");
  	$openids = self::ASSERT_group_openids($query['togroup'], $db);

    $newsid = $query["newsid"] + 0;
    $media_id = $db->get("wx_news", "media_id", ["id"=>$newsid]);
    if(!$media_id) return json_error(2000, "消息ID错误");

    $json = json_decode( WX::send_sendall_news($openids, $media_id), true);
    if($json['errcode'] != 0)return json_error(2000, "推送群发文本消息失败", ["A"=>$json, "openids"=>$openids]); //发送失败

    return json_OK([]);
  }
  
  

	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 用微信客户端上传一个图片
	 * xxx.com/wx/uploadimage?imageid=IMAGEID (GET )
	 * 
	 * 返回: 相对链接
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function  uploadimage(&$get, &$post, &$db){
		$query = &$get;
		$posts = ["uid","t","sign", "module",
				"imageid"] ;//必须的参数
		foreach($posts as $k)if(!isset($query[$k]))return json_error(1000, "参数缺失:$k"  );		
	  return json_OK(["imageurl"=>self::imageid2local($query, $db)]);
	}
	public static function  imageid2local(&$query, &$db){
		$ms = microtime(true) * 10000;
		$uid = $query['uid'] + 0;
		$url = "/downloads/upload/$uid-$ms.jpg";
		$fn = $_SERVER['DOCUMENT_ROOT'] . $url;
		WX::download_media($query['imageid'], $fn);
		//生成缩略图96*96
		G::make_mini_jpg($fn, 96);
		return $url;
	}
	
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 用微信客户端上传一个录音
	 * xxx.com/wx/uploadvoice?imageid=IMAGEID (GET )
	 * 
	 * 返回: 相对链接
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function  uploadvoice(&$get, &$post, &$db){
		$query = &$post;
		$posts = ["uid","t","sign", "module",
				"serverid"] ;//必须的参数
		foreach($posts as $k)if(!isset($query[$k]))return json_error(1000, "参数缺失:$k"  );
		
		$ms = microtime(true) * 10000;
		$uid = $query['uid'] + 0;
		$url = "/downloads/upload/$uid-$ms.amr";
		$fn = $_SERVER['DOCUMENT_ROOT'] . $url;
		WX::download_media($query['serverid'], $fn);
		$db->insert("wx_voice", ["serverid"=>"{$query['serverid']}", "url"=>$url, "time"=>time()]);
	  return json_OK(["url"=>$url]);
	}
	
	public static function  get_voice_serverid(&$url, &$db){
		if(WX_OK == "ERROR") return "ERROR";//用于显示一个声音块
		$row = $db->get("wx_voice", "*", ["url"=>$url]);
		$time = time();
		if(!$row){//没有服务端数据
			$fn = $_SERVER['DOCUMENT_ROOT'] . $url;
			if(!file_exists($fn))return false;//录音文件丢失
			$json = WX::upload_media("voice", $fn);
			$serverid = $json['media_id'];
			$db->insert("wx_voice", ["serverid"=>$serverid, "url"=>$url, "time"=>$time]);
			return $serverid;
		}
		if($time - $row['time'] > 71*3600){//服务端数据过期
			$fn = $_SERVER['DOCUMENT_ROOT'] . $url;
			if(!file_exists($fn))return false;//录音文件丢失
			$json = WX::upload_media("voice", $fn);
			$serverid = $json['media_id'];
			$db->update("wx_voice", ["serverid"=>$serverid, "time"=>$time], ["url"=>$url]);
			return $serverid;
		}
		//服务端数据仍然有效：
		return $row['serverid'];
	}
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 获取声音的微信服务端ID, 以逗号分隔的字符串=>[serverId, url]数组
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function str_audios_to_list(&$db, $str_audios){
		if(! $str_audios) return [];
		$arr = explode(',', $str_audios);
		$R = [];
		foreach($arr as $url){
			$serverid = class_wx::get_voice_serverid($url, $db);
			if(!$serverid)continue;//录音文件丢失, 所以无法上传
			$R[] = ["serverId"=>$serverid, "url"=>$url];//保存上传后的音频id
		}
		return $R;
	}
	
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 获取推荐二维码
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function get_my_refer_QRcode_id(&$db, $openid){
		$userinfo = QgsModules::GetUser(["id","name"], ["openid"=>"$openid"]);
		//固定文件名：
		$url = "/downloads/QRcode/9/{$userinfo['id']}.png";
		$fn = $_SERVER['DOCUMENT_ROOT'] . $url;
		//if(!file_exists($fn))
		{
      //获取二维码, 10001-100000,前1万个号留做更重要的时候用。
			$s = WX::getQrcodeTextUrlLong($userinfo['id'] - 190000);
			//生成二维码图片到服务器：
			class_qrcode::save_png($fn, $s, $userinfo['name']."向您推荐请高手平台");
			//QRcode::png($s, $fn, 2, 9, 4, false, $userinfo['name']."向你推荐请高手平台");
		}
		//上传到微信
		$json = WX::upload_media("image", $fn);
		$serverid = $json['media_id'];
		return $serverid;
	}
	
	
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 生成临时带参数二维码
	 * xxx.com/wx/qrcodetmp?sid=SID (GET )
	 * 
	 * 返回: 图片链接
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function  qrcodetmp(&$get, &$post, &$db){
		$query = &$get;
		$posts = ["uid","t","sign", "module",
				"sid"] ;//必须的参数
		foreach($posts as $k)if(!isset($query[$k]))return json_error(1000, "参数缺失:$k"  );
		
		$url = WX::getQrcodeUrlTmp($query['sid']);
	  return json_OK(["url"=>$url]);
	}
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 生成永久带参数二维码
	 * xxx.com/wx/qrcodetextlong?n=SID (GET )
	 * 
	 * 返回: 二维码解析后的地址
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function  qrcodetextlong(&$get, &$post, &$db){
		$query = &$get;
		$posts = ["uid","t","sign", "module",
				"n"] ;//必须的参数
		foreach($posts as $k)if(!isset($query[$k]))return json_error(1000, "参数缺失:$k"  );
		
    //获取二维码, 10001-100000,前1万个号留做更重要的时候用。
		$url = WX::getQrcodeTextUrlLong($query['n'] - 190000);
	  return json_OK(["url"=>$url]);
	}
	
  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 扫描带参数二维码
   * 返回: 推送给用户的消息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function  qrcodescan($db, $me, $sid){
    //专家信息
    if($sid > 100000){
      $row = $db->get("z_user_sys", "*", ["id"=>$sid]);
      if(!$row) return "专家信息已失效或不存在: $sid";
      $mobile = substr($row['mobile'], 0,7) . "****";
      return "你即将使用以下专家信息："
        ."\n"
        ."\n姓名：{$row['name']}"
        ."\n单位：{$row['company']}"
        ."\n手机：$mobile"
        ."\n"
        ."\n确认无误请:<a href='". self::wx_url("#/frame/usesid?eid=$sid")."'>点击进入</a>";
    }
    //邀请朋友：
    if($sid > 10000){
      //推广二维码，加上19万为推广者的用户id
      $sid += 190000;
      $new_eid = class_user::setreferee($db, $me, $sid);
      return $new_eid;
    }

    //小于1万的永久二维码
    return "系统保留二维码";
  }
	
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 发出提问时，推送消息
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function notice_ToAsk(&$db, &$qa){
		if($qa['type']=="快速抢答"){
			$qaid = $qa['id'] + 0;
			$content = mb_substr($qa['content'], 0 , 20, 'UTF-8');
			if($content != $qa['content'])$content .= "... ?";
			$url = self::wx_url("#/frame/sq-qa-show?qaid=$qaid");
			$text = "有一个新的快速抢答：\n\n{$content}".
							"\n\n<a href='$url'>开始抢答</a>";
			//查找所有接收对象
			$openids = QgsModules::SelectUser("openid", ["AND"=>["attr[~]"=>'"alwaysrecivesq":"true"']]);
			
			foreach($openids as $openid){
				//测试 客服接口
				$json = json_decode(WX::send_service_text($openid, $text), true);
				if($json['errcode'] == 0)continue; //发送成功
			}
			
			//var_dump($openids); return;
		  //测试 群发接口，每人每天只收4个
			//$json = json_decode(WX::send_sendall_text($openids, $text), true);
			//if($json['errcode'] == 0)return true; //发送成功
		}
	}

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 回答时效过半时，推送消息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function notice_pass_half(&$db, &$qa){
    if($qa['type']=="快速抢答"){
      $qaid = $qa['id'] + 0;
      $content = mb_substr($qa['content'], 0 , 20, 'UTF-8');
      if($content != $qa['content'])$content .= "... ?";
      $url = self::wx_url("#/frame/service-qa-show?qaid=$qaid");
      $text = "有一个新的快速抢答：\n\n{$content}".
              "\n\n<a href='$url'>开始抢答</a>";
      //查找所有接收对象
      $openids = QgsModules::SelectUser("openid", ["AND"=>["attr[~]"=>'"alwaysrecivesq":"true"']]);
      
      foreach($openids as $openid){
        //测试 客服接口
        $json = json_decode(WX::send_service_text($openid, $text), true);
        if($json['errcode'] == 0)continue; //发送成功
      }
      
      //var_dump($openids); return;
      //测试 群发接口，每人每天只收4个
      //$json = json_decode(WX::send_sendall_text($openids, $text), true);
      //if($json['errcode'] == 0)return true; //发送成功
    }
  }
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 专家开始回答问题提醒
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function noticeS_EBeginAnswer(&$db, $uid, $qaid){
		$url = self::wx_url("#/frame/service-qa-show?qaid=$qaid");
		$text = "专家已开始回答(QAID:$qaid)，<a href='$url'>点击查看</a>";
	
		//看看可不可以发客服消息
		$openid = QgsModules::GetUser("openid", ["id"=>$uid]);
		if(self::sendtextnotice($db, $openid, $text, "service"))return; //发送成功
		
		//没办法了，就用模板了：
		$data = ["专家收到回答问题提示，现已回应：",
				"专家已开始回答(QAID:$qaid)",//事件类型
				G::s_now(), //事件时间
				"点击查看"
			];
		WX::SendTPL($openid, "事件提醒", $data, $url);
	}
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 专家提交回答提醒
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function notice_ESubmitAnswer(&$db, &$qa){
		$qaid = $qa['id'];
	
		//推送消息给提问用户
		$url = self::wx_url("#/frame/user-qa-show?qaid=$qaid");
		$text = "专家提交回答(QAID:$qaid)，<a href='$url'>点击查看</a>";
		$openid = QgsModules::GetUser("openid", ["id"=>$qa['userid']]);
		if(!self::sendtextnotice($db, $openid, $text, "user")){
			$data = ["你的提问已回答完成(QAID:$qaid)：",
					"回答已提交",//事件类型
					G::s_now(), //事件时间
					"点击查看"
				];
			WX::SendTPL($openid, "事件提醒", $data, $url);
		}
	
		//推送消息给提问编导
		$url = self::wx_url("#/frame/service-qa-show?qaid=$qaid");
		$text = "专家提交回答(QAID:$qaid)，<a href='$url'>点击查看</a>";
		$openid = QgsModules::SelectUser("openid", ["id"=>$qa['pointerid']]);
		if(!self::sendtextnotice($db, $openid, $text, "service")){//多人的时候，失败也不知道
			$data = ["专家回答完成(QAID:$qaid)：",
					"回答已提交",//事件类型
					G::s_now(), //事件时间
					"点击查看"
				];
			WX::SendTPL($openid, "事件提醒", $data, $url);
		}
	}
	
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 专家放弃回答提醒
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function notice_EDropAnswer(&$db, $qaid, $pointerid){
		//推送消息给提问编导
		$url = self::wx_url("#/frame/service-qa-show?qaid=$qaid");
		$text = "专家放弃回答(QAID:$qaid)，<a href='$url'>点击查看</a>";
		$openid = QgsModules::SelectUser("openid", ["id"=>$pointerid]);
		if(!self::sendtextnotice($db, $openid, $text, "service")){
			$data = ["专家放弃回答(QAID:$qaid)：",
					"回答已放弃",//事件类型
					G::s_now(), //事件时间
					"点击查看"
				];
			WX::SendTPL($openid, "事件提醒", $data, $url);
		}
	}
	/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
	 * 用户提交评价提醒
	 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
	public static function notice_USaySatisfy(&$db, &$qa, $points){
		$qaid = $qa['id'];
		
		//推送消息给提问专家
		if($qa['satisfy'] == "满意")foreach($qa['answerid'] as $answerid){
		  $url = self::wx_url("#/frame/expert-qa-show?qaid=$qaid");
			$userinfo = QgsModules::GetUser(["openid", "name"], ["id"=>$answerid]);
			$openid = $userinfo['openid'];
			$totlepoints = $db->sum("z_userpoints", "n", ["AND"=>["userid"=>$answerid, "attr"=>"expertpoints"]]);
			WX::SendTPL($userinfo['openid'], "积分变动通知", ["您的积分增加啦!",
						$userinfo['name']     ,//用户名
						G::s_now()            ,//时间
						$points / 2           , //积分变动
						$totlepoints          ,//积分余额
						"您的回答 用户满意"   ,//变动原因
						"高手无私，回答有价"
					], $url);
		}
	
		//推送消息给提问编导
		$url = self::wx_url("#/frame/service-qa-show?qaid=$qaid");
		$text = "回答(QAID:$qaid)已评价:{$qa['satisfy']}，<a href='$url'>点击查看</a>";
		$openid = QgsModules::SelectUser("openid", ["id"=>$qa['pointerid']]);
		if(!self::sendtextnotice($db, $openid, $text, "service")){
			$data = ["回答已评价(QAID:$qaid)：",
					"评价已提交({$qa['satisfy']})",//事件类型
					G::s_now(), //事件时间
					"点击查看"
				];
			WX::SendTPL($qa['pointerid'], "事件提醒", $data, $url);
		}
	}
	

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 推送消息：指定专家
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function notice_toanswer(&$db, $qaid, $content_full, $answerid, $userid){
    $content = mb_substr($content_full, 0 , 20, 'UTF-8');
    if($content != $content_full)$content .= "... ?";
    $url = self::wx_url("#/frame/expert-qa-show?qaid=$qaid");
    $openid = QgsModules::GetUser("openid", ["id"=>$answerid]);
    $ask_openid = QgsModules::GetUser("openid", ["id"=>$userid]);

    if(self::sendnewsnotice($openid, "请您处理提问......", $content, $url, "http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-2.jpg")){
      //一个问答在第二次指定专家时，不推送该消息（一般情况下，在48小时之内会进行本操作，所以推送成功的概率高）：
      if($ask_openid)self::sendnewsnotice($ask_openid, "您的提问正在处理中......", "$content", self::wx_url("#/frame/user-qa-show?qaid=$qaid"), "http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-1.jpg");
      return; //发送成功
    }

    $text = "请您处理提问......\n\n{$content}\n\n<a href='$url'>详情</a>";

    //看看可不可以发文本消息
    if(self::sendtextnotice2($db, $openid, $text))return; //发送成功

    //没办法了，就用模板了：
    $timelimit = $db->get("qa_data", "v", ["AND"=>["qaid"=>$qaid, "attr"=>"timelimit"]]);
    $major = $db->get("qa_data", "v", ["AND"=>["qaid"=>$qaid, "attr"=>"major"]]);
    $data = ["您有一个问题等待回答：",
        $content,//待办内容
        $major, //基本信息
        $timelimit, //待办时间
        "点击查看"
      ];
    WX::SendTPL($openid, "待办事项提醒", $data, $url);
  }
  /* ------------------------------------------
   * 推送消息：时效过半
   * ------------------------------------------ */
  public static function notice_passhalf(&$db, $qaid, $attr){
    //获取所有编导和专家id
    $answers = $db->select("qa_data", ["userid", "senderid", "state"], ["AND"=>["parentid"=>$qaid], "ORDER"=>"id DESC"]);
    $service_ids = [];
    $answer_ids = [];
    foreach($answers as $row){
      if($row['state'] != "待回答")break;
      $service_ids[$row['senderid']] = $row['senderid'];
      $answer_ids [$row['userid'  ]] = $row['userid'  ];
    }
    //获取所有编导和专家openid
    $openids = QgsModules::SelectUser(["id", "openid"], ["id"=>array_merge($service_ids, $answer_ids)]);
    $service_openids = [];
    $answer_openids = [];
    foreach($openids as $row){
      if($service_ids[$row['id']])$service_openids[] = $row['openid'];
      if($answer_ids [$row['id']])$answer_openids [] = $row['openid'];
    }
    
    $content = mb_substr($attr["content"], 0 , 30, 'UTF-8');
    if($content != $attr["content"])$content .= "... ?";
    
    //通知编导：
    $text = "问答时效已过半，您指定的专家尚未及时完成回答\n\n<a href='". self::wx_url("#/frame/service-qa-show?qaid=$qaid"). "'>详情</a>";
    foreach($service_openids as $openid){
      $url = self::wx_url("#/frame/service-qa-show?qaid=$qaid");
      if(!self::sendnewsnotice($openid, "请您尽快处理提问......", "【专家尚未完成回答】$content", $url, "http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-3.jpg")){
        if(!self::sendtextnotice2($db, $openid, "请您尽快处理提问......\n\n【专家尚未完成回答】$content\n\n<a href='$url'>详情</a>")){
          // 发送模板消息，目前没有这个模板
        }
      }
    }
    //催促专家：
    foreach($answer_openids as $openid){
      $url = self::wx_url("#/frame/expert-qa-show?qaid=$qaid");
      if(!self::sendnewsnotice($openid, "请您尽快处理提问......", "$content", $url, "http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-3.jpg")){
        if(!self::sendtextnotice2($db, $openid, "请您尽快处理提问......\n\n$content\n\n<a href='$url'>详情</a>")){
          // 发送模板消息，目前没有这个模板
        }
      }
    }
    //$text = "问答时效已过半，请您尽快回答\n\n<a href='". self::wx_url("#/frame/expert-qa-show?qaid=$qaid"). "'>详情</a>";
    //foreach($answer_openids as $openid)self::sendtextnotice($db, $openid, $text);
  }
  /* ------------------------------------------
   * 推送消息：时效过半
   * ------------------------------------------ */
  //public static function notice_passhalf_old(&$db, $qaid){
  //  //获取所有编导和专家id
  //  $answers = $db->select("qa_data", ["userid", "senderid", "state"], ["AND"=>["parentid"=>$qaid], "ORDER"=>"id DESC"]);
  //  $service_ids = [];
  //  $answer_ids = [];
  //  foreach($answers as $row){
  //    if($row['state'] != "待回答")break;
  //    $service_ids[$row['senderid']] = $row['senderid'];
  //    $answer_ids [$row['userid'  ]] = $row['userid'  ];
  //  }
  //  //获取所有编导和专家openid
  //  $openids = QgsModules::SelectUser(["id", "openid"], ["id"=>array_merge($service_ids, $answer_ids)]);
  //  $service_openids = [];
  //  $answer_openids = [];
  //  foreach($openids as $row){
  //    if($service_ids[$row['id']])$service_openids[] = $row['openid'];
  //    if($answer_ids [$row['id']])$answer_openids [] = $row['openid'];
  //  }
  //  //通知编导：
  //  $text = "问答时效已过半，您指定的专家尚未及时完成回答\n\n<a href='". self::wx_url("#/frame/service-qa-show?qaid=$qaid"). "'>详情</a>";
  //  foreach($service_openids as $openid)self::sendtextnotice($db, $openid, $text);
  //  //催促专家：
  //  $text = "问答时效已过半，请您尽快回答\n\n<a href='". self::wx_url("#/frame/expert-qa-show?qaid=$qaid"). "'>详情</a>";
  //  foreach($answer_openids as $openid)self::sendtextnotice($db, $openid, $text);
  //}
  /* ------------------------------------------
   * 推送消息：回答完成
   * ------------------------------------------ */
  public static function notice_qaanswered(&$db, $qa){
    $qaid = $qa['id'];
    //获取用户openid
    $openid = QgsModules::GetUser("openid", ["id"=>$qa['userid']]);

    $attr = $qa['attr'];
    $content = mb_substr($attr["content"], 0 , 30, 'UTF-8');
    if($content != $attr["content"])$content .= "... ?";
    $url = self::wx_url("#/frame/user-qa-show?qaid=$qaid");
    if(!self::sendnewsnotice($openid, "您的提问已经处理，请评价......", "$content", $url, "http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-4.jpg")){
      if(!self::sendtextnotice2($db, $openid, "您的提问已经处理，请评价......\n\n$content\n\n<a href='$url'>详情</a>")){
        // 发送模板消息，目前没有这个模板
      }
    }
    //通知专家：
    //$text = "您的提问已回答，请您予以评价\n\n<a href='". self::wx_url("#/frame/user-qa-show?qaid=$qaid"). "'>详情</a>";
    //self::sendtextnotice($db, $openid, $text);
  }


  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 尝试推送图文消息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function sendnewsnotice($openid, $title, $text, $url, $img){
    $ret = WX::json_send_service_news($openid, $title, $text, $url, $img);
    $json = json_decode($ret, true);
    if($json['errcode'] != 0){
      error_log($ret);
    }
    return $json['errcode'] == 0; //发送成功
  }



  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 尝试推送文本消息
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function sendtextnotice(&$db, $openid, $text, $module="user"){
    if($openid > 0)$openid = QgsModules::GetUser("openid", ["id"=>$openid]);
    if(!$openid) return false;
    //测试 客服接口
    $json = json_decode(WX::send_service_text($openid, $text), true);
    if($json['errcode'] == 0)return true; //发送成功

    if($module=="service")return false; //编导不给其它接口。

    //测试 预览接口
    $json = json_decode(WX::send_preview_text($openid, $text), true);
    if($json['errcode'] == 0)return true; //发送成功

    //测试 群发接口
    $json = json_decode(WX::send_sendall_text($openid, $text), true);
    if($json['errcode'] == 0)return true; //发送成功

    return false;
  }

  /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   * 尝试推送文本消息（不使用客服接口）
   * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
  public static function sendtextnotice2(&$db, $openid, $text, $module="user"){
    if($openid > 0)$openid = QgsModules::GetUser("openid", ["id"=>$openid]);
    if(!$openid) return false;

    //测试 预览接口
    $json = json_decode(WX::send_preview_text($openid, $text), true);
    if($json['errcode'] == 0)return true; //发送成功

    //测试 群发接口
    $json = json_decode(WX::send_sendall_text($openid, $text), true);
    if($json['errcode'] == 0)return true; //发送成功

    return false;
  }
}

?>