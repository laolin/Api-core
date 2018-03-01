<?php
/**
 * 微信模块
 *
 * 1.1 wx_token/access_token: 获取 access_token
 * 1.2 wx_token/JsApiTicket:  获取 JsApiTicket
 *
 * 2.1 wx/appid:        获取微信公众号appid
 * 2.2 wx/code_login:   用code换取微信openid。 凭此，可转接bind+user模块换取用户登录票据和uid
 * 2.3 wx/jsapi_ticket: 前端请求jsapi签名
 * 2.4 wx/wx_info:      根据[微信openid/微信unionid]，获取微信呢称、头像等
 *
 * 3.1 user/verify_token:  根据票据和签名，进行用户登录，获取uid
 * 3.2 user/create_token:  根据uid, 生成票据并返回。【仅限本服务器，或白名单】
 * 3.3 * user/password:      修改用户密码
 * 3.4 * user/logout:        退出用户登录
 * 3.5 * user/login:         根据[uid/nick]和密码登录，获取票据和uid
 * 3.6 * user/register:      注册用户，返回票据和uid
 *
 * 4.1 * sms/send_verification: 获取获取随机手机验证码，并向用户发送，返回验证码id
 * 4.2 * sms/verify_code:       用验证码及其id进行校验，正确时返回手机号
 *
 * 5.1 bind/bind:     绑定用户[微信openid/微信unionid/手机号]
 * 5.2 bind/get_bind: 获取指定uid绑定情况，同时包含[微信openid/微信unionid/手机号]
 * 5.2 bind/get_uid:  根据[微信openid/微信unionid/手机号], 获取用户uid
 *
 */
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING );
ini_set("display_errors", 1);
ini_set("error_log", "php_errors.log");

define('MY_SPACENAME', 'NApiUnit');

// 自动加载类库
require_once('apis/class-loader.php');



require_once('../index.api-shell.php');
// 加载配置
search_require('config.inc.php');
// 开启调试信息
DJApi\API::enable_debug(true);
//输出
apiShellCall(MY_SPACENAME);
