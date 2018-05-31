<?php
namespace MyClass;

use DJApi;

class CUser {

  /**
   * 根据用户名密码签名，进行用户登录，获取登录信息
   *
   * @request uid/nick:
   * @request timestamp: 5分钟之内
   * @request sign: 签名 = md5($uid.$timestamp)
   *
   * @return {id, uid, tokenid, token}
   */
  public static function login($query) {
    $nick = $query['uid'];if (!$nick) {
      $nick = $query['nick'];
    }

    $db = CDbBase::db('qgs_user');
    /**　读用户行 */
    $userRow = $db->get(CDbBase::table('user', 'qgs_user'), ['id', 'uid', 'password'], ['AND' => [
      'OR' => ['id' => $nick, 'uid' => $nick, 'nick' => $nick],
    ]]);
    \DJApi\API::debug(['读用户行', 'DB' => $db->getShow(), 'userRow' => $userRow]);
    if (!is_array($userRow)) {
      return \DJApi\API::error(\DJApi\API::E_NEED_LOGIN, '登录失败');
    }
    $uid = $userRow['uid'];

    $timestamp = $query['timestamp'];
    $sign = $query['sign'];
    $post = [
      'uid' => $uid,
      'sign' => $sign,
      'timestamp' => $timestamp,
    ];

    $json = \DJApi\API::post(SERVER_API_ROOT, "user/user/login", $post);

    $json['datas']['id'] = $userRow['id'];

    return $json;
  }

  /**
   * 根据微信 code 登录，获取登录信息
   * @request code:
   * @request name: 公众号名称
   *
   * @return {id, uid, tokenid, token}
   */
  public static function wx_code_login($query) {
    $jsonLogin = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_code_to_token_uid", $query);
    if (!\DJApi\API::isOk($jsonLogin)) {
      return $jsonLogin;
    }
    $uid = $jsonLogin['datas']['uid'];

    $db = CDbBase::db('qgs_user');
    /**　读用户行 */
    $userRow = $db->get(CDbBase::table('user', 'qgs_user'), ['id', 'password'], ['uid' => $uid]);
    \DJApi\API::debug(['读用户行', 'DB' => $db->getShow(), 'userRow' => $userRow]);
    if (!is_array($userRow)) {
      // 是新用户，要生成一行
      $password = md5("@qgs");
      // 获取绑定的openid
      $jsonBindOpenid = \DJApi\API::post(SERVER_API_ROOT, "user/bind/get_bind", [
        'uid'=>$uid,
        'bindtype'=>'wx-openid',
        'param1'=>DJApi\Configs::get("WX_APPID_APPSEC_DEFAULT")['WX_APPID'],
      ]);
      \DJApi\API::debug(['请求绑定数据' => $jsonBindOpenid]);
      $openid = $jsonBindOpenid['datas']['binds'][0]['value'];
      $userRow = [
        'uid'=>$uid,
        'password'=>$password,
        'openid'=>$openid,
      ];
      $userRow['id'] = $db->insert(CDbBase::table('user', 'qgs_user'), $userRow);
    }
    $id = $userRow['id'];

    $jsonLogin['datas']['id'] = $userRow['id'];
    $jsonLogin['datas']['password'] = $userRow['password'];

    return $jsonLogin;
  }

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function verify($query) {
    $tokenid = $query['tokenid'];
    $timestamp = $query['timestamp'];
    $sign = $query['sign'];

    return \DJApi\API::post(SERVER_API_ROOT, "user/user/verify_token", [
      'tokenid' => $tokenid,
      'timestamp' => $timestamp,
      'sign' => $sign,
    ]);
  }

  /** 用签名换取 uid
   * 数据来源：api请求
   * @return bool
   */
  public static function sign2uid($query) {
    return self::verify($query)['datas']['uid'];
  }

  /** 验证签名
   * 数据来源：api请求
   * @return bool
   */
  public static function checkSign($query) {
    return \DJApi\API::isOk(self::verify($query));
  }

  /**
   * 用户是否有权限
   */
  public static function hasRight($uid, $name) {
    return true;

    $db = DJApi\DB::db();
    return $db->has(CDbBase::$table['user_right'], ['AND' => [
      'uid' => $uid,
      'name' => $name,
      't1[>]' => '2000-01',
      'OR' => [
        't2' => '',
        't2[>]' => \DJApi\API::now(),
      ],
    ]]);
  }

  /**
   * 读取用户等个人信息
   *
   * @return {me}
   */
  public static function info($uid) {
    $db = DJApi\DB::db();
    $user = $db->get(CDbBase::$table['user'], '*', ['uid' => $uid]);
    $attr = json_decode($user['attr'], true);
    \DJApi\API::debug(['用户个人信息', "DB" => $db->getShow()]);

    /** 用户权限 */
    $rights = self::getUserRightArray($uid);

    $wxJson = \DJApi\API::post(SERVER_API_ROOT, "user/mix/wx_infos", [
      'uid' => $uid,
      'timestamp' => $timestamp,
      'sign' => $sign,
    ]);
    \DJApi\API::debug(['微信', $wxJson]);
    $wx = $wxJson['datas']['list'][0];
    return \DJApi\API::OK([
      'me' => [
        'rights' => $rights,
        'attr' => $attr,
        'uid' => $uid,
        'wx' => $wx,
      ],
    ]);
  }

  /**
   * 保存用户个人信息
   */
  public static function save_info($uid, $attr) {
    $db = DJApi\DB::db();

    $user = $db->get(CDbBase::$table['user'], '*', ['uid' => $uid]);

    if (is_array($user)) {
      if (!$user['attr']) {
        $user['attr'] = '{}';
      }

      $attrOld = json_decode($user['attr'], true);
      if (!is_array($attrOld)) {
        $attrOld = [];
      }

      $attr = array_merge($attrOld, $attr);
      $db->update(CDbBase::$table['user'], ['attr' => \DJApi\API::cn_json($attr)], ['uid' => $uid]);
    } else {
      $db->insert(CDbBase::$table['user'], [
        'uid' => $uid,
        't1' => '2000-01',
        'attr' => \DJApi\API::cn_json($attr),
      ]);
    }
    \DJApi\API::debug(['保存用户个人信息', "DB" => $db->getShow()]);
    return \DJApi\API::OK([]);
  }

  /**
   * 读取所有用户权限(数组)
   */
  public static function getUserRightArray($uid) {
    $db = DJApi\DB::db();
    $list = $db->select(CDbBase::$table['user_right'], 'name', ['AND' => [
      'uid' => $uid,
      't1[>]' => '2000-01',
      'OR' => [
        't2' => '',
        't2[>]' => \DJApi\API::now(),
      ],
    ]]);
    \DJApi\API::debug(['读取所有用户权限', "DB" => $db->getShow()]);
    return $list;
  }

  /**
   * 读取所有用户权限
   */
  public static function getUserRight($uid) {
    $list = self::getUserRightArray($uid);

    if (!is_array($list) || !count($list)) {
      return \DJApi\API::error(\DJApi\API::E_NEED_RIGHT, '没有任何权限');
    }

    return \DJApi\API::OK(['list' => $list]);
  }

}
