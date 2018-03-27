<?php
/**
 * 混合调用模块
 */
namespace NApiUnit;

class class_wx_answer
{
  /**
   * 统一入口，只允许 https 调用
   */
  public static function API($functionName, $request)
  {
    if (!method_exists(__CLASS__, $functionName)) {
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }
    if (!\DJApi\API::is_https()) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, "请使用https");
    }
    return self::$functionName($request);
  }

  /**
   * 接口： data/event
   * 微信公众号事件
   *
   * @request event: 公众号事件, json 字符串
   *
   * @return null
   */
  public static function event($request)
  {
    $event = json_decode($request->query["event"], true);
    // 在log 中记录一下
    error_log('微信公众号事件' . \DJApi\API::cn_json($event));
    $appid = $event['ToUserName'];
    $openid = $event['FromUserName'];
    $type = $event['MsgType']; // 通常是 "event"
    $eventName = $event['Event']; // 关注 = subscribe, 取消关注 = unsubscribe, 点击菜单链接 = VIEW
    $text = $event['Content']; // 用户发送过来的 文本

    if ($type == 'event') {
      $fn = "_event_$eventName";
      if (method_exists(__CLASS__, $fn)) {
        return self::$fn($event);
      }
    }
    return [];
  }

  /**
   * 处理事件 subscribe: 用户关注
   */
  public static function _event_subscribe($event)
  {
    $openid = $event['FromUserName'];
    $app_wxid = $event['ToUserName'];
    list($appid, $secret) = WxTokenBase::appid_appsec($app_wxid);

    $wxUser = CWxBase::getWxUser($openid, $appid, $secret);
    if ($wxUser['openid']) {
      $data = CWxBase::saveWxInfo($wxUser, "openid");
    }
    error_log('关注, 保存' . ($data ? '成功' : '失败'));

    return ['关注, 保存数据 ' . ($data ? '成功' : '失败')];
  }

  /**
   * 处理事件 subscribe: 用户 取消关注
   */
  public static function _event_unsubscribe($event)
  {
    $openid = $event['FromUserName'];
    $db = CDbBase::db();
    $data = ['subscribe' => 0, 'timeupdate'=>time()];
    $where = ['openid' => $openid];
    $n = $db->update(CDbBase::table('wx_user'), $data, $where);
    error_log('取消关注, 保存数据 ' . ($n ? '成功' : '失败'));
    return ['取消关注, 保存数据 ' . ($n ? '成功' : '失败')];
  }
}
