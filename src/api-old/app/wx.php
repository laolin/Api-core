<?php
require_once("g.php");
require_once("app/user.class.php");

class WX{
	// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
	// ┃              获取 Token                                                      ┃
	// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
	static function GetToken(){
		$db = QgsModules::DB();
		$rows = $db->select("g_settings", "*", [ "k1"=>'access_token']);
		$t_now = time();
		if (isset($rows[0],$rows[0]['v1'])){
			$t_old = $rows[0]["v2"] + 0;
			if ($t_now - $t_old < 7000) return $rows[0]["v1"];//旧的全局Token仍然有效
		}

		//获取新的 全局Token:
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential"
				   . "&appid=" . APPID . "&secret=" . SECRET;
		$r = G::httpGet($url);
		
		$dick_token = json_decode($r);
		if(isset($dick_token->errcode)) return "";

		//得到了：
		$token = $dick_token->access_token;
		if(strlen($token)<10)return "";
		//写入数据库，以后可以再用：
		if (isset($rows[0],$rows[0]['v1'])){
			$db->update("g_settings", ["v1"=>$token,"v2"=>$t_now], array("k1"=>'access_token'));
		}else{
			$db->insert("g_settings", array("v1"=>$token,"v2"=>$t_now,"k1"=>'access_token'));
		}
		return $token;
	}
	static function GetJsApiTicket() {
		$db = QgsModules::DB();
		$rows = $db->select("g_settings", "*", [ "k1"=>'jsapi_ticket']);
		$t_now = time();
		if (isset($rows[0],$rows[0]['v1'])){
			$t_old = $rows[0]["v2"] + 0;
			if ($t_now - $t_old < 7000) return $rows[0]["v1"];//旧的全局token仍然有效
		}

		//获取新的 全局Token:
		$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".self::GetToken();
		$res = json_decode(G::httpGet($url));
		if(!isset($res->errcode) || $res->errcode != 0) return "";
		$ticket = $res->ticket;
		if (!$ticket) return "";

		//写入数据库，以后可以再用：
		if (isset($rows[0],$rows[0]['v1'])){
			$db->update("g_settings", array("v1"=>$ticket,"v2"=>$t_now), array("k1"=>'jsapi_ticket'));
		}else{
			$db->insert("g_settings", array("v1"=>$ticket,"v2"=>$t_now,"k1"=>'jsapi_ticket'));
		}
		return $ticket;
	}
	
	
	
    
	// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
	// ┃             发送模板消息                                                     ┃
	// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

	/// <summary> 发送模板消息 </summary>
	/// <param name="touser">接收用户的openid</param>
	/// <param name="tplName">模板名称</param>
	/// <param name="data">发送的数据</param>
	/// <param name="url">详情的链接</param>
	/// <returns></returns>
	static function SendTPL($openid, $tplName, $data, $url){
    if(is_array($openid)){
      $R = [];
      foreach($openid as $subid) $R[] = self::SendTPL($subid, $tplName, $data, $url);
      return $R;
    }
		if(is_int($openid)){
			$db = new medoo();
			$rows = QgsModules::SelectUser("openid", ["id"=>$openid]);
			if (!isset($rows, $rows[0])) return false;
			$openid = "{$rows[0]}";
		}
		$openid = trim($openid,"*");
		if(strlen($openid)<10)return;
		$D = array();
		$K = array();
		$D["touser"] = $openid;
		$D["url"] = $url;
		$D["topcolor"] = "#FFCC00";
		switch ($tplName){
			case "待办事项提醒":
				$D["template_id"] = "Nv4ZwapozItcKb6VvkcCk7m1-UjkqLEss8xwMuAAQj8";
				$K["first"] = DataItem($data[0], "#223388");
				$K["keyword1"] = DataItem($data[1], "#223388");//待办内容
				$K["keyword2"] = DataItem($data[2], "#223388");//基本信息
				$K["keyword2"] = DataItem($data[3], "#223388");//时间
				$K["remark"] = DataItem($data[4], "#223388");
				break;
				
			case "事件提醒":
				$D["template_id"] = "kozCB47mLD-pjuXstTGa3fnocSIog0MBit0QY0nWkgk";
				$K["first"] = DataItem($data[0], "#223388");
				$K["keyword1"] = DataItem($data[1], "#223388");//事件类型
				$K["keyword2"] = DataItem($data[2], "#223388");//事件时间
				$K["remark"] = DataItem($data[3], "#223388");
				break;
				
			case "积分变动通知":
				$D["template_id"] = "jGjTYn5JxCufcL44h4PDxgTX46WcbNF-tXgdQoaOjWY";
				$K["first"] = DataItem($data[0], "#223388");
				$K["keyword1"] = DataItem($data[1], "#223388");//用户名
				$K["keyword2"] = DataItem($data[2], "#223388");//时间
				$K["keyword3"] = DataItem($data[3], "#223388");//积分变动
				$K["keyword4"] = DataItem($data[4], "#223388");//积分余额
				$K["keyword5"] = DataItem($data[5], "#223388");//变动原因
				$K["remark"] = DataItem($data[6], "#223388");
				break;
		}

		$D["data"] = $K;
		$send_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . self::GetToken();
		$json = urldecode(json_encode($D));//G::JSON_from($D);

		return G::post($send_url,$json );
	}
    
    
    
	// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
	// ┃             getOpenid                                                        ┃
	// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
    
	static function getOpenid($code){
		$url = "https:" . "//api.weixin.qq.com/sns/oauth2/access_token?appid=" . APPID
				. "&secret=" . SECRET
				. "&code=" . $code
				. "&grant_type=authorization_code";
		$res = G::httpGet($url);
		$user_info = json_decode($res,true);
		if(!isset($user_info['openid']))return "";
		return $user_info['openid'];
	}

	#region 获取用户基本信息（包括UnionID机制）

	/// <summary>  根据OPENID获取用户头像等信息 </summary>
	static function getUserInfoByOpenid($openid){
		return json_decode(self::read_WX_userinfo($openid), true);
	}

	/*获取用户基本信息（包括UnionID机制）

	开发者可通过OpenID来获取用户基本信息。请使用https协议。

	接口调用请求说明

	http请求方式: GET
	https://api.weixin.qq.com/cgi-bin/user/info?access_token=ACCESS_TOKEN&openid=OPENID&lang=zh_CN
	参数说明

	参数	是否必须	说明
	access_token	 是	 调用接口凭证
	openid	 是	 普通用户的标识，对当前公众号唯一
	lang	 否	 返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
	返回说明

	正常情况下，微信会返回下述JSON数据包给公众号：

	{
		"subscribe": 1, 
		"openid": "o6_bmjrPTlm6_2sgVt7hMZOPfL2M", 
		"nickname": "Band", 
		"sex": 1, 
		"language": "zh_CN", 
		"city": "广州", 
		"province": "广东", 
		"country": "中国", 
		"headimgurl":    "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/0", 
	   "subscribe_time": 1382694957,
	   "unionid": " o6_bmasdasdsad6_2sgVt7hMZOPfL"
	}
	参数说明

	参数	说明
	subscribe	 用户是否订阅该公众号标识，值为0时，代表此用户没有关注该公众号，拉取不到其余信息。
	openid	 用户的标识，对当前公众号唯一
	nickname	 用户的昵称
	sex	 用户的性别，值为1时是男性，值为2时是女性，值为0时是未知
	city	 用户所在城市
	country	 用户所在国家
	province	 用户所在省份
	language	 用户的语言，简体中文为zh_CN
	headimgurl	 用户头像，最后一个数值代表正方形头像大小（有0、46、64、96、132数值可选，0代表640*640正方形头像），用户没有头像时该项为空。若用户更换头像，原有头像URL将失效。
	subscribe_time	 用户关注时间，为时间戳。如果用户曾多次关注，则取最后关注时间
	unionid	 只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段。详见：获取用户个人信息（UnionID机制）
	错误时微信会返回错误码等信息，JSON数据包示例如下（该示例为AppID无效错误）:

	{"errcode":40013,"errmsg":"invalid appid"}
	 */
	static function read_WX_userinfo($openid){
		$url = "https:/"."/api.weixin.qq.com/cgi-bin/user/info?access_token=" .self::GetToken()
					. "&openid=" . $openid
					. "&lang=zh_CN";
		return G::httpGet($url);
	}
	#endregion


	#region 获取二维码

	/// <summary>  生成临时带参数二维码 </summary>
	static function getQrcodeUrlTmp($scene_id){
		$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" .self::GetToken();
		$s = G::post($url, "{\"expire_seconds\": 604800, \"action_name\": \"QR_SCENE\", \"action_info\": {\"scene\": {\"scene_id\": $scene_id }}}");
		$dick = json_decode($s, 1);
		if (isset($dick["url"])) return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$dick["ticket"];
		return false;
	}

	/// <summary>  生成永久的带参数二维码 </summary>
	static function getQrcodeUrlLong($scene_id)
	{
		$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . self::GetToken();
		$s = G::post($url, "{ \"action_name\": \"QR_LIMIT_SCENE\", \"action_info\": {\"scene\": {\"scene_id\": $scene_id }}}");
		$dick = json_decode($s, 1);
		if (isset($dick["url"])) return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$dick["ticket"];
		//if (isset($dick["url"])) return $dick["url"];
		return false;
	}

	/// 永久的带参数二维码解析后的地址
	static function getQrcodeTextUrlLong($scene_id)
	{
		$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . self::GetToken();
		$s = G::post($url, "{ \"action_name\": \"QR_LIMIT_SCENE\", \"action_info\": {\"scene\": {\"scene_id\": $scene_id }}}");
		$dick = json_decode($s, 1);
		//var_dump($dick);
		//var_dump($s);
		//if (isset($dick["url"])) return "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$dick["ticket"];
		if (isset($dick["url"])) return $dick["url"];
		return false;
	}



	/// <summary> 网站登陆链接后，获取用户 openid </summary>
	static function get_openid($code){
		return json_decode(self::getTokenByCode($code), true);
	}



	#region 网站登陆链接后，获取用户头像等信息(第一步，自动运行下面三步)
	/// <summary> 网站登陆链接后，获取用户头像等信息 </summary>
	static function getUserInfo($code)
	{
		$dick_token = json_decode(self::getTokenByCode($code), true);
		if ( isset($dick_token["errcode"])) return $dick_token;

		$user_info = array();

		$dick_refresh_token = json_decode(self::refresh_token($dick_token["refresh_token"]), true);
		if ( isset($dick_refresh_token["errcode"])){
			//详细信息失败，可能是用snsapi_base模式获取授权的。
			$user_info["openid"] = $dick_token["openid"];
			return $user_info;
		}

		$user_info = json_decode(self::WX_get_userinfo_by_access_token($dick_refresh_token["access_token"], $dick_refresh_token["openid"]), true);
		if ( isset($user_info["errcode"])){
			//详细信息失败，可能是用snsapi_base模式获取授权的。
			$user_info["openid"] = $dick_token["openid"];
		}

		return $user_info;
	}
	#endregion

	#region 第二步：通过code换取网页授权access_token
	/*
	第二步：通过code换取网页授权access_token

	首先请注意，这里通过code换取的是一个特殊的网页授权access_token,与基础支持中的access_token（该access_token用于调用其他接口）不同。公众号可通过下述接口来获取网页授权access_token。如果网页授权的作用域为snsapi_base，则本步骤中获取到网页授权access_token的同时，也获取到了openid，snsapi_base式的网页授权流程即到此为止。

	请求方法

	获取code后，请求以下链接获取access_token： 
	https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code
	参数说明

	参数	是否必须	说明
	appid	 是	 公众号的唯一标识
	secret	 是	 公众号的appsecret
	code	 是	 填写第一步获取的code参数
	grant_type	 是	 填写为authorization_code
	返回说明

	正确时返回的JSON数据包如下：

	{
	   "access_token":"ACCESS_TOKEN",
	   "expires_in":7200,
	   "refresh_token":"REFRESH_TOKEN",
	   "openid":"OPENID",
	   "scope":"SCOPE",
	   "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
	}
	参数	描述
	access_token	 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
	expires_in	 access_token接口调用凭证超时时间，单位（秒）
	refresh_token	 用户刷新access_token
	openid	 用户唯一标识，请注意，在未关注公众号时，用户访问公众号的网页，也会产生一个用户和公众号唯一的OpenID
	scope	 用户授权的作用域，使用逗号（,）分隔
	unionid	 只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段。详见：获取用户个人信息（UnionID机制）

	错误时微信会返回JSON数据包如下（示例为Code无效错误）:

	{"errcode":40029,"errmsg":"invalid code"}
 
	 */
	static function getTokenByCode($code)
	{
		$url = "https:" . "//api.weixin.qq.com/sns/oauth2/access_token?appid=" . APPID
				. "&secret=" . SECRET
				. "&code=" . $code
				. "&grant_type=authorization_code";
		return G::httpGet($url);
	}

	#endregion

	#region 第三步：刷新access_token（如果需要）
	/*
	由于access_token拥有较短的有效期，当access_token超时后，可以使用refresh_token进行刷新，refresh_token拥有较长的有效期（7天、30天、60天、90天），当refresh_token失效的后，需要用户重新授权。

	请求方法

	获取第二步的refresh_token后，请求以下链接获取access_token： 
	https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=APPID&grant_type=refresh_token&refresh_token=REFRESH_TOKEN
	参数	是否必须	说明
	appid	 是	 公众号的唯一标识
	grant_type	 是	 填写为refresh_token
	refresh_token	 是	 填写通过access_token获取到的refresh_token参数
	返回说明

	正确时返回的JSON数据包如下：

	{
	   "access_token":"ACCESS_TOKEN",
	   "expires_in":7200,
	   "refresh_token":"REFRESH_TOKEN",
	   "openid":"OPENID",
	   "scope":"SCOPE"
	}
	参数	描述
	access_token	 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
	expires_in	 access_token接口调用凭证超时时间，单位（秒）
	refresh_token	 用户刷新access_token
	openid	 用户唯一标识
	scope	 用户授权的作用域，使用逗号（,）分隔

	错误时微信会返回JSON数据包如下（示例为Code无效错误）:

	{"errcode":40029,"errmsg":"invalid code"}
//*/
	static function refresh_token($refresh_token)
	{
		$url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?"
					. "appid=" . APPID
					. "&grant_type=refresh_token"
					. "&refresh_token=" . $refresh_token;
		return G::httpGet($url);
	}
	#endregion

	#region 第四步：拉取用户信息(需scope为 snsapi_userinfo)
	/*
	第四步：拉取用户信息(需scope为 snsapi_userinfo)

	如果网页授权作用域为snsapi_userinfo，则此时开发者可以通过access_token和openid拉取用户信息了。

	请求方法

	http：GET（请使用https协议）
	https://api.weixin.qq.com/sns/userinfo?access_token=ACCESS_TOKEN&openid=OPENID&lang=zh_CN
	参数说明

	参数	描述
	access_token	 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
	openid	 用户的唯一标识
	lang	 返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
	返回说明

	正确时返回的JSON数据包如下：

	{
	   "openid":" OPENID",
	   " nickname": NICKNAME,
	   "sex":"1",
	   "province":"PROVINCE"
	   "city":"CITY",
	   "country":"COUNTRY",
		"headimgurl":    "http:// wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46", 
		"privilege":[
		"PRIVILEGE1"
		"PRIVILEGE2"
		],
		"unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
	}
	参数	描述
	openid	 用户的唯一标识
	nickname	 用户昵称
	sex	 用户的性别，值为1时是男性，值为2时是女性，值为0时是未知
	province	 用户个人资料填写的省份
	city	 普通用户个人资料填写的城市
	country	 国家，如中国为CN
	headimgurl	 用户头像，最后一个数值代表正方形头像大小（有0、46、64、96、132数值可选，0代表640*640正方形头像），用户没有头像时该项为空。若用户更换头像，原有头像URL将失效。
	privilege	 用户特权信息，json 数组，如微信沃卡用户为（chinaunicom）
	unionid	 只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段。详见：获取用户个人信息（UnionID机制）

	错误时微信会返回JSON数据包如下（示例为openid无效）:

	{"errcode":40003,"errmsg":" invalid openid "}
	 */
	static function WX_get_userinfo_by_access_token ($access_token, $openid)
	{
		$url = "https://api.weixin.qq.com/sns/userinfo?"
					. "access_token=" . $access_token
					. "&openid=" . $openid
					. "&lang=zh_CN";
		return G::httpGet($url);
	}
	#endregion
	
	#region 发送客服消息

	/*
	客服接口-发消息

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
	请注意，如果需要以某个客服帐号来发消息（在微信6.0.2及以上版本中显示自定义头像），则需在JSON数据包的后半部分加入customservice参数，例如发送文本消息则改为：

	{
		"touser":"OPENID",
		"msgtype":"text",
		"text":
		{
			 "content":"Hello World"
		},
		"customservice":
		{
			 "kf_account": "test1@kftest"
		}
	}
	*/
	
	
	
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃                    给指定openid发送文本消息                                  ┃
  // ┃                                                                              ┃
  // ┃  预览接口，每天只限100条                                                     ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_preview_text($openid, $text){
    $url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token=" . self::GetToken();
    $param = '{ "touser":"'.$openid.'", "msgtype":"text", "text": { "content":"'.$text.'"}}';
    $ret = G::post($url, $param);
    G::db_log("预览文本消息: $openid, $text, $ret");
    return $ret;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃                给指定openid(或openid列表)发送文本消息                        ┃
  // ┃                                                                              ┃
  // ┃  群发接口，每天只限100条，但是在“开发者中心”里显示是1000条？               ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_sendall_text($openids, $text){
    if(is_string($openids))$openids = [$openids];
    if(count($openids) == 1){
      //return self::send_preview_text($openids[0], $text);
      //弱智的微信，群发时，竟然要求至少2个用户！不知道加个虚构的openid结果如何。
      $openids[] = "od6xzv00t______iwPN_P1Km3PH4";//嘿嘿，头尾一样的给一个就可以了
    }
    $url = "https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=" . self::GetToken();
    $param = json_encode(["touser"=>$openids,"msgtype"=>"text", "text"=>["content"=>$text]], JSON_UNESCAPED_UNICODE);
    $ret = G::post($url, $param);
    G::db_log("群发文本消息: ".json_encode($openids).", $text, $ret");
    return $ret;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃                给指定openid发送图文预览                                      ┃
  // ┃                                                                              ┃
  // ┃  群发接口，每天只限100条                                                     ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_preview_news($openid, $media_id){
    $url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token=" . self::GetToken();
    $param = json_encode(["touser"=>$openid,"msgtype"=>"mpnews", "mpnews"=>["media_id"=>$media_id]], JSON_UNESCAPED_UNICODE);
    $ret = G::post($url, $param);
    G::db_log("预览图文消息: ".json_encode($openid).", $media_id, $ret");
    return $ret;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃                给指定openid列表(单个则发预览)发送图文消息                    ┃
  // ┃                                                                              ┃
  // ┃  群发接口，每天只限100条，但是在“开发者中心”里显示是1000条？               ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_sendall_news($openids, $media_id){
    if(is_string($openids))$openids = [$openids];
    if(count($openids) == 1){
      //return self::send_preview_news($openids[0], $media_id);
      //弱智的微信，群发时，竟然要求至少2个用户！不知道加个虚构的openid结果如何。
      $openids[] = "od6xzv00t______iwPN_P1Km3PH4";
    }
    $url = "https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=" . self::GetToken();
    $param = json_encode(["touser"=>$openids,"msgtype"=>"mpnews", "mpnews"=>["media_id"=>$media_id]], JSON_UNESCAPED_UNICODE);
    $ret = G::post($url, $param);
    G::db_log("群发图文消息: ".json_encode($openids).", $media_id, $ret");
    return $ret;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃                给所有用户发送图文消息                                        ┃
  // ┃                                                                              ┃
  // ┃  群发接口，每天只限100条，但是在“开发者中心”里显示是1000条？               ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_sendall_news_all($media_id){
    $url = "https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=" . self::GetToken();
    $param = json_encode([ "filter"=>["is_to_all"=>true], "mpnews"=>["media_id"=>$media_id], "msgtype"=>"mpnews"], JSON_UNESCAPED_UNICODE);
    $ret = G::post($url, $param);
    G::db_log("群发图文消息: ".json_encode($openids).", $media_id, $ret");
    return $ret;
  }
  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃                    给指定openid发送文本消息                                  ┃
  // ┃                                                                              ┃
  // ┃  客服接口，48小时内无互动会发送失败。鄙视微信：没有让手机用户自己决定关闭    ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_service_text($openid, $text){
    if(is_array($openid)){
      foreach($openid as $subid)self::send_service_text($subid, $text);
      return "I don't know.";
    }
    $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . self::GetToken();
    $param = '{ "touser":"'.$openid.'", "msgtype":"text", "text": { "content":"'.$text.'"}}';
    $ret = G::post($url, $param);
    G::db_log("客服接口: $openid, $text, $ret");
    return $ret;
  }

  static function send_service_news($openid, $title, $description, $newsurl, $picurl){
    if(is_array($openid)){
      foreach($openid as $subid)self::send_service_news($subid, $title, $description, $newsurl, $picurl);
      return "I don't know.";
    }
    $ret = self::json_send_service_news($openid, $title, $description, $newsurl, $picurl);      ;
    //var_dump($ret);
    $dick = json_decode($ret, true);
    //var_dump($dick);
    return isset($dick["errcode"]) && $dick["errcode"] == "0";
  }

  static function json_send_service_news($openid, $title, $description, $newsurl, $picurl){
    //客服接口，24小时内无互动会发送失败。
    $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . self::GetToken();
    $param = json_encode(["touser"=>$openid,"msgtype"=>"news", "news"=>["articles"=>[
        ["title"=>$title,
         "description"=>$description,
         "url"=>$newsurl,
         "picurl"=>$picurl]
      ]]], JSON_UNESCAPED_UNICODE);
    return G::post($url, $param);
  }

  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃  发放酬金专用     ydZVnh7VC0duYYE7meby0i_0zYnO-NsJXLa1SJYizN8                ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_payexpert_news($openid, $money, $payType){
    //先看48小时内有没有互动：
    $contentIndex = [
        "回答"=>"感谢您的专业回答，本次发放酬金{$money}元。",
        "裁判"=>"感谢您的专业裁判，本次发放酬金{$money}元。",
        "推广"=>"感谢您的大力推广，本次发放酬金{$money}元。",
        "支持"=>"感谢您的大力支持，本次发放酬金{$money}元。"
      ];
    $imgIndex = [
        "回答"=>"http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-5.jpg",
        "裁判"=>"http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-6.jpg",
        "推广"=>"http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-7.jpg",
        "支持"=>"http://qgs.oss-cn-shanghai.aliyuncs.com/sys/msg/msg-8.jpg"
      ];
    $content = $contentIndex[$payType];
    if(!$content) return;

    $title = "酬金到位通知";
    $picurl = $imgIndex[$payType];
    $linkurl = "http://qinggaoshou.com/qgs/wx?hasf=%2Fframe%2Fmyaccount";
    if(self::send_service_news($openid, $title, $content, $linkurl, $picurl)) return;

    //没有的话，就修改永久素材，再发送：
    $url = "https://api.weixin.qq.com/cgi-bin/material/update_news?access_token=" . self::GetToken();
    //$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token=" . self::GetToken();
    $media_id = "ydZVnh7VC0duYYE7meby0i_0zYnO-NsJXLa1SJYizN8";
    $pic_id   = "ydZVnh7VC0duYYE7meby0pSxLg-3b3KErafy3J3j5aw";
    $param = json_encode([
      "media_id"=>$media_id,
      "index"=>0,
      "articles"=>[
        "title"=>$title,
        "thumb_media_id"=>$pic_id,
        "author"=> "作者",
        "digest"=> $content,
        "show_cover_pic"=> 1,
        "content"=>  "高手无私，回答有价。请高手已为您发放酬劳。<br><br><a href='$linkurl'>点击查看详情</a><br><br>或点击下方“查看原文”查看详情。",
        "content_source_url"=> $linkurl
      ]], JSON_UNESCAPED_UNICODE);
    G::post($url, $param);//修改永久素材，暂时不判断成功与否
    
    return self::send_preview_news($openid, $media_id);
    return self::send_sendall_news($openid, $media_id);
  }

  // ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
  // ┃  推荐帖子专用     NgvKjC3erAmGNjr2Ji5idlTzidvQMooa-hdifyUefY0                ┃
  // ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  static function send_bbs_recommend_news($bbs_list){
    
    //修改永久素材，发送给全部：
    $url = "https://api.weixin.qq.com/cgi-bin/material/update_news?access_token=" . self::GetToken();
    //$url = "https://api.weixin.qq.com/cgi-bin/message/mass/preview?access_token=" . self::GetToken();
    $media_id = "NgvKjC3erAmGNjr2Ji5idlTzidvQMooa-hdifyUefY0";
    $pic_id   = "NgvKjC3erAmGNjr2Ji5idrwQaf15M6UbVJDVdyzefF8";
    $title = "请高手真心邀请您参加高手论谈！";
    $content = "高手无私，论谈无价。请高手平台精心为您推荐高手论谈精华，真心邀请您共同参与。";
    $linkurl = WX_DOMAIN . "/qgs/wx?hash=%23%2Fframe%2Fbbs-list-user";
    $param = json_encode([
      "media_id"=>$media_id,
      "index"=>0,
      "articles"=>[
        "title"=>$title,
        "thumb_media_id"=>$pic_id,
        "author"=> "作者",
        "digest"=> $content,
        "show_cover_pic"=> 1,
        "content"=>  "高手无私，论谈无价。请高手平台精心为您推荐高手论谈精华，真心邀请您共同参与。".
                     "<br><br>".implode($bbs_list, "<br>").
                     "<br><br><a href='$linkurl'>点击查看更多</a>".
                     "<br><br>或点击下方“查看原文”查看更多高手论谈内容。",
        "content_source_url"=> $linkurl
      ]], JSON_UNESCAPED_UNICODE);
    G::post($url, $param);//修改永久素材，暂时不判断成功与否
    
    return "";
    
    return self::send_sendall_news_all($media_id);
  }
  
  
  
  
  
  
  
  
  
  
  
	
	// ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
	// ┃  同步公众号文章列表到本地数据库                                              ┃
	// ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
	static function sync_news($db = false){
		//获取总数：
		$url = "https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token=" . self::GetToken();
		$ret = G::httpGet($url);	
		$arr = json_decode($ret, true);
		$news_count = $arr["news_count"];//图文总数量

		$url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=" . self::GetToken();
		if(!$db)$db = new mymedoo();
		for($offset=0; $offset<$news_count; $offset+=20){
			$param = '{ "type":"news", "offset":' . $offset. ', "count":20}';
			$ret = G::post($url, $param);
			$arr = json_decode($ret, true);
			$items = $arr["item"];
			foreach($items as $item){
			  $item0 = & $item["content"]["news_item"][0];
			  
				$url = "/downloads/upload/thumb_media_{$item0['thumb_media_id']}.jpg";
				$fn = $_SERVER['DOCUMENT_ROOT'] . $url;
				WX::download_get_material($item0['thumb_media_id'], $fn);
		
				$db->surerow("wx_news", [
					"n"           =>count($item ["content"]["news_item"]),
					"pic_id"      =>$item0["thumb_media_id"],
					"pic_url"     =>$url,
					"title"       =>$item0["title"],
					"url"         =>$item0["url"],
					"digest"      =>$item0["digest"],
					"content"     =>$item0["content"],
					"update_time" =>$item ["update_time"]],
				 ["media_id"    =>$item ["media_id"]]
				);
			}
		}
		return $ret;
	}
	

	//下载永久素材，不判断文件名，由本函数调用者指定文件名：
	static function download_get_material($media_id, $fn){
		$url = "https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=" . self::GetToken();
	  $param = '{"media_id":"'.$media_id.'"}';
		$res = G::post($url, $param);
		G::saveFile($fn, $res);
	}
	
	//下载媒体文件，不判断文件名，由本函数调用者指定文件名：
	static function download_media($media_id, $fn){
		$url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=" . self::GetToken() . "&media_id=$media_id";
		$res = G::httpGet($url);
		G::saveFile($fn, $res);
	}

	//上传媒体文件，不判断文件名，由本函数调用者指定文件名：
	static function upload_media($type, $fn){
		$url = "http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token=" . self::GetToken() . "&type=$type";
		$param = ["media"=>"@" .$fn]; 
		$ret = G::post($url, $param);
		return json_decode($ret, true);
	}


    
}


function DataItem($value, $color){
	return array("value"=>$value, "color"=>$color);
}






?>