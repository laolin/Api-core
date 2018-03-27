<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
ini_set("display_errors", 1);
ini_set("error_log", "wx_answer.log.php");

/**
 * 告诉多个服务器，用户事件
 * 不要以 / 结尾
 **/
$serverList = [
  'https://api.qinggaoshou.com/wx/wx-event',
];

//-------- 接入验证: ----------------------------------------------------------
define("TOKEN", "pgyxxkj");
if (isset($_GET['echostr'])) {
  if (checkSignature()) {
    echo $_GET["echostr"];
  }
  exit;
}
function checkSignature()
{
  $signature = $_GET["signature"];
  $timestamp = $_GET["timestamp"];
  $nonce = $_GET["nonce"];
  $token = TOKEN;
  $tmpArr = array($token, $timestamp, $nonce);
  // use SORT_STRING rule
  sort($tmpArr, SORT_STRING);
  $tmpStr = implode($tmpArr);
  $tmpStr = sha1($tmpStr);
  return $tmpStr == $signature;
}

//-------- 事件响应: ----------------------------------------------------------
if (isset($GLOBALS["HTTP_RAW_POST_DATA"])) {
  $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
}
if (empty($postStr)) {
  echo "";
  exit;
}
libxml_disable_entity_loader(true);
$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

error_log('微信事件, json=' . json_encode($postObj, JSON_UNESCAPED_UNICODE));

// 通知各 api 有事件
foreach ($serverList as $server) {
  $r = httpPost("$server/data/event", ['event' => json_encode($postObj, JSON_UNESCAPED_UNICODE)]);
  error_log("post请求： server=$server, R=" . json_encode($r, 256));
}

function httpPost($url, $param)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_REFERER, "http://pgy");
  curl_setopt($ch, CURLOPT_POST, 1);
  if (is_array($param) && count($param) > 0) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
  } else if (is_string($param)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
  } else {
    curl_setopt($ch, CURLOPT_POSTFIELDS, []);
  }
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //信任任何证书
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 检查证书中是否设置域名,0不验证
  $output = curl_exec($ch);
  //error_log('post请求：' . json_encode(['httpPost', 'info'=>curl_getinfo($ch), 'error'=>curl_error($ch), '返回'=>$output], 256));
  curl_close($ch);
  return $output;
}

//-------- 以下代码暂时没去用: ----------------------------------------------------------

/** 发出 post 请求，不等待返回，继续执行后面代码 */
function postOnly($full_url, $data)
{
  $parse = parse_url($full_url);
  preg_match('|^http(?P<https>s)?\:\/\/(?P<url>(?P<host>[^\/]+))(?P<path>\/.*)$|', $url, $match);
  $port = $match['https'] ? 443 : 80;
  $host = $match['host'];
  $url = ($match['https'] ? 'ssl' : 'http') . '://' . $match['url'];
  $url = $match['url'];
  $path = $match['path'];

  //you will need to setup an array of fields to post with
  //then create the post string
  $formdata = $data;
  if (is_array($formdata)) {
    foreach ($formdata as $key => $val) {
      $poststring .= urlencode($key) . "=" . urlencode($val) . "&";
    }
    $poststring = substr($poststring, 0, -1);
  } else {
    $poststring = $formdata;
  }

  echo "url=", $url, $port;return;
  $fp = fsockopen($url, $port, $errno, $errstr, $timeout = 30);

  if (!$fp) {
    //error tell us
    echo "$errstr ($errno)\n";
  } else {
    //send the server request
    fputs($fp, "POST $path HTTP/1.1\r\n");
    fputs($fp, "Host: $host\r\n");
    fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
    fputs($fp, "Content-length: " . strlen($poststring) . "\r\n");
    fputs($fp, "Connection: close\r\n\r\n");
    fputs($fp, $poststring . "\r\n\r\n");

    if (false) {
      while (!feof($fp)) {
        echo fgets($fp, 4096);
      }
    }
    //close fp - we are done with it
    fclose($fp);
  }
}

function ResponseMsg($postObj, &$db)
{
  $RX_TYPE = trim($postObj->MsgType);
  $openid = "{$postObj->FromUserName}";
  switch ($RX_TYPE) {
    case "text":
      if (function_exists("my_wx_answer_text")) {
        my_wx_answer_text($db, $postObj);
      }

      //else answer_text($postObj);
      return;
    case "image":
    case "location":
    case "voice":
    case "video":
    case "link":
      break;
    case "event":
      switch ($postObj->Event) {
        case "subscribe": //关注事件的回复内容
          $wxinfo = WX::getUserInfoByOpenid($openid);
          QgsShare::saveWxUser($db, $wxinfo, "已关注");
          $me = $db->get(QGS_DB_TABLE_QGS_USER, "*", ["openid" => $openid]);
          if ($postObj->EventKey && strlen($postObj->EventKey) > 10) {
            $sid = substr($postObj->EventKey, 8) + 190000;
            if ($sid < 200001) {
              $sid = 200001;
            }

            if (!$me) {
              $me = ["openid" => $openid, "password" => strtoupper(MD5(PASSWORD_APPEND)), "parentid" => $sid, "userstate" => 3];
              $me['id'] = $db->insert(QGS_DB_TABLE_QGS_USER, $me);
              //生成一个用户
              QgsShare::log("用户关注，openid=$openid, uid={$me['id']}, parentid=$sid  新用户");
            } else {
              $oldParentid = $me["parentid"] + 0;
              $needBind = $oldParentid <= 200001 && $sid != $me["id"] + 0 && $sid > 200001;
              if ($needBind) {
                $db->update(QGS_DB_TABLE_QGS_USER, ["parentid" => $sid], ["id" => $me["id"]]);
                //绑定上级用户
              }
              QgsShare::log("用户关注，openid=$openid, uid={$me['id']}, parentid=$sid, 绑定=" . ($needBind ? "Yes" : "No") . "  旧用户");
            }
            scan_key($postObj, $db, $sid);
            return;
          } else {
            if (!$me) {
              $me = ["openid" => $openid, "password" => strtoupper(MD5(PASSWORD_APPEND)), "parentid" => 200001, "userstate" => 3];
              $me['id'] = $db->insert(QGS_DB_TABLE_QGS_USER, $me);
              //生成一个用户
              QgsShare::log("用户关注，openid=$openid, uid={$me['id']}, parentid=$sid  新用户");
            }
            QgsShare::log("用户关注，openid=$openid, uid={$me['id']}, " . ($me ? "旧用户" : "新用户"));
          }
          answer_news_ID($postObj, "ABOUTWELCOME");
          return;
        case "unsubscribe":
          $uid = $db->get(QGS_DB_TABLE_QGS_USER, "id", ["openid" => $openid]);
          QgsShare::log("用户取消关注，openid=$openid, uid=$uid");
          $db->update(QGS_DB_TABLE_WX_USER, ["isgz" => "已取消"], ["openid" => "{$openid}"]);
          return;
        case "LOCATION":
          //上报地理位置
          $db->insert(QGS_DB_TABLE_QGS_LOCATION, [
            "time" => time(),
            "latd" => $postObj->Latitude,
            "lotd" => $postObj->Longitude,
            "prec" => $postObj->Precision]);
          break;
        case "SCAN":
          //扫描二维码
          //带有参数？
          if ($postObj->EventKey) {
            $sid = "{$postObj->EventKey}"+190000;
            $me = $db->get(QGS_DB_TABLE_QGS_USER, "*", ["openid" => "{$openid}"]);
            if (!$me) {
              $me = ["openid" => $openid, "password" => strtoupper(MD5(PASSWORD_APPEND)), "parentid" => $sid, "userstate" => 3];
              $me['id'] = $db->insert(QGS_DB_TABLE_QGS_USER, $me);
              //生成一个用户
              QgsShare::log("推荐码，原已关注，openid=$openid, uid={$me['id']}, parentid={$me['parentid']}, 推荐人id=$sid");
            } else {
              $oldParentid = $me["parentid"] + 0;
              if ($oldParentid <= 200001 && $sid > 200001 && $sid != $me["id"] + 0) {
                $db->update(QGS_DB_TABLE_QGS_USER, ["parentid" => $sid], ["id" => $me["id"]]);
                //绑定上级用户
                QgsShare::log("推荐码，绑定，openid=$openid, uid={$me['id']}, parentid=$sid");
              } else {
                QgsShare::log("推荐码，原已绑定，openid=$openid, uid={$me['id']}, parentid={$me['parentid']}, 推荐人id=$sid");
              }
            }
            scan_key($postObj, $db, $sid);
            return;
          }
          break;
        case "VIEW":
          QgsShare::log("$openid VIEW {$postObj->EventKey}", "event.log");
          //更新微信信息
          QgsShare::updateWxUser($db, $openid);
          break;
        case "CLICK":
          QgsShare::log("$openid CLICK {$postObj->EventKey}", "event.log");
          //更新微信信息
          QgsShare::updateWxUser($db, $openid);
          switch ($postObj->EventKey) {
            case "ABOUTWELCOME":
            case "ABOUTRULE":
            case "ABOUTFAQ":
            case "ABOUTNOTICE":
            case "ABOUTCASE":
              answer_news_ID($postObj);
              return;
            default:
              answer_text($postObj);
              return;
          }
        default:
          break;
      }
      break;
    default:
      answer_text($postObj);
      break;
  }
}
function scan_key($postObj, $db, $sid)
{
  if (function_exists("my_scan_key")) {
    return my_scan_key($postObj, $db, $sid);
  }

  answer_text($postObj);
}

//回复消息
function answer_text($postObj, $str = false)
{
  if (!$str) {
    $str = "您好！欢迎关注" . $GLOBALS['WX_GZH_NAME'] . "！";
  }

  echo //
  ("<xml>
        <ToUserName><![CDATA[{$postObj->FromUserName}]]></ToUserName>
        <FromUserName><![CDATA[{$postObj->ToUserName}]]></FromUserName>
          <CreateTime>" . time() . "</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[$str]]></Content>
            </xml>");
}

//回复图片
function answer_image($postObj, $imgid)
{
  //WX::send_service_text($postObj->FromUserName, $str);return;
  echo //
  ("<xml>
          <ToUserName><![CDATA[{$postObj->FromUserName}]]></ToUserName>
          <FromUserName><![CDATA[{$postObj->ToUserName}]]></FromUserName>
          <CreateTime>" . time() . "</CreateTime>
          <MsgType><![CDATA[image]]></MsgType>
          <Image>
            <MediaId><![CDATA[{$imgid}]]></MediaId>
          </Image>
        </xml>");
}

//回复图文消息
function answer_news($postObj, $items)
{
  $str = "<xml>
      <ToUserName><![CDATA[{$postObj->FromUserName}]]></ToUserName>
      <FromUserName><![CDATA[{$postObj->ToUserName}]]></FromUserName>
      <CreateTime>" . time() . "</CreateTime>
      <MsgType><![CDATA[news]]></MsgType>
      <ArticleCount>" . count($items) . "</ArticleCount>
      <Articles>";
  foreach ($items as $item) {
    $str .= "<item>
        <Title><![CDATA[{$item['title']}]]></Title>
        <Description><![CDATA[{$item['description']}]]></Description>
        <PicUrl><![CDATA[{$item['picurl']}]]></PicUrl>
        <Url><![CDATA[{$item['url']}]]></Url>
      </item>";
  }

  $str .= "</Articles>
      </xml>";
  echo $str;
}

//回复消息
function answer_news_ID($postObj, $EventKey = false)
{
  if (!$EventKey) {
    $EventKey = $postObj->EventKey;
  }

  if (function_exists("my_answer_news_ID")) {
    return my_answer_news_ID($postObj, $EventKey);
  }

  answer_text($postObj);
}

//连接客服
function connect_kf($postObj, $kf = "")
{
  echo //
  ("<xml>
        <ToUserName><![CDATA[{$postObj->FromUserName}]]></ToUserName>
        <FromUserName><![CDATA[{$postObj->ToUserName}]]></FromUserName>
        <CreateTime>" . time() . "</CreateTime>
        <MsgType><![CDATA[transfer_customer_service]]></MsgType>
      </xml>");
}
