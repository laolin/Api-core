<?php
namespace NApiUnit;

class class_comment
{
  /**
   * 统一入口，用户验证
   */
  public static function API($functionName, $request)
  {
    if (!method_exists(__CLASS__, $functionName)) {
      return \DJApi\API::error(\DJApi\API::E_FUNCTION_NOT_EXITS, '参数无效', [$functionName]);
    }
    $verify = \MyClass\CUser::verify($request->query);
    if (!\DJApi\API::isOk($verify)) {
      return $verify;
    }
    $uid = $verify['datas']['uid'];

    return self::$functionName($request, $uid);
  }

  /**
   * 数据库升级转换
   */
  public static function updateDB($request, $uid)
  {
    if ($uid != 301173) {
      return \DJApi\API::error(-99, '无权限');
    }

    $db = CDbBase::db();

    /** 先清空 */
    $db->query("TRUNCATE TABLE `unit_comment`");

    /** 读旧数据 */
    $old_feed = $db->select("api_tbl_feed", "*", ['AND' => ['del[!]' => '1', 'flag[!]' => 'draft']]);
    $old_comment = $db->select("api_tbl_comment", "*", ['AND' => ['mark[!]' => '1']]);
    \DJApi\API::debug(['old_feed' => $old_feed, 'DB' => $db->getShow()]);

    // 转换主贴
    $datas = [];
    foreach ($old_feed as $row) {
      $attr = [
        'content' => $row['content'],
      ];
      if($row['pics']){
        $attr['pics'] = explode(",", $row['pics']);
      }
      $new_row = [
        'id' => $row['fid'],
        'uid' => $row['uid'],
        'module' => $row['app'],
        'mid' => substr($row['cat'], -5),
        'type' => $row['flag'],
        'cid' => '',
        'attr' => \DJApi\API::cn_json($attr),
        't_update' => $row['update_at'] ? $row['update_at'] : $row['publish_at'],
      ];
      $datas[] = $new_row;
      if (count($datas) > 100) {
        $db->insert("unit_comment", $datas);
        $datas = [];
      }
    }
    $db->insert("unit_comment", $datas);
    \DJApi\API::debug(['datas' => $datas, 'DB' => $db->getShow()]);

    // 转换回贴、赞
    $feed_datas = [];
    foreach ($old_feed as $row) {
      $feed_datas[$row['fid']] = $row;
    }
    $datas = [];
    foreach ($old_comment as $row) {
      $feed_data = $feed_datas[$row['fid']];
      $attr = [
        'content' => $row['content'],
      ];
      $new_row = [
        //'id' => $row['fid'],
        'uid' => $row['uid'],
        'module' => $feed_data['app'],
        'mid' => substr($feed_data['cat'], -5),
        'type' => $row['ctype'],
        'cid' => $row['fid'],
        'attr' => \DJApi\API::cn_json($attr),
        't_update' => $row['create_at'],
      ];
      $datas[] = $new_row;
      if (count($datas) > 100) {
        $db->insert("unit_comment", $datas);
        $datas = [];
      }
    }
    $db->insert("unit_comment", $datas);
    \DJApi\API::debug(['datas' => $datas, 'DB' => $db->getShow()]);

    $db->update("unit_comment", ['type' => 'praise'], ['type' => 'like']);
    $db->update("unit_comment", ['type' => 'feedback'], ['type' => 'comment']);

    
    $db->update("unit_comment", ['module' => 'steeFacComment'], ['AND'=>['module' => 'steeComment', 'mid[<]'=>20000]]);
    $db->update("unit_comment", ['module' => 'steeProjComment'], ['AND'=>['module' => 'steeComment', 'mid[>]'=>20000]]);

    return \DJApi\API::OK([]);
  }

  /**
   * 接口： comment/li
   * 列出指定模块和mid所有主贴
   * @request module: 必选
   * @request mid: 必选
   * @request limit: 可选, 列表范围, 1~2个数字, 同SQL
   *
   * @return list
   */
  public static function li($request, $uid)
  {
    return CComment::li($request->query, $uid);
  }

  /**
   * 接口： comment/remove
   * 删除一条主贴
   * @request module: 必选
   * @request mid: 必选
   * @request cid: 要删除的评论
   *
   * @return []
   */
  public static function remove($request, $uid)
  {
    return CComment::remove($request->query, $uid);
  }

  /**
   * 接口： comment/add
   * 添加一条主贴
   * @request module: 必选
   * @request mid: 必选
   * @request data: 评论数据
   *
   * @return list
   */
  public static function add($request, $uid)
  {
    return CComment::add($request->query, $uid);
  }

  /**
   * 接口： comment/feedback
   * 回复一条评论
   * @request module: 必选
   * @request mid: 必选
   * @request cid: 要评论的主贴的id
   * @request fuid: 要回复的用户
   * @request content: 要回复的内容
   *
   * @return list
   */
  public static function feedback($request, $uid)
  {
    return CComment::feedback($request->query, $uid);
  }

  /**
   * 接口： comment/removeFeedback
   * 删除一条回复
   * @request module: 必选
   * @request mid: 必选
   * @request cid: 回复的id
   *
   * @return list
   */
  public static function removeFeedback($request, $uid)
  {
    return CComment::removeFeedback($request->query, $uid);
  }

  /**
   * 接口： comment/praise
   * 赞主贴
   * @request module: 必选
   * @request mid: 必选
   * @request cid: 必选
   *
   * @return list
   */
  public static function praise($request, $uid)
  {
    return CComment::praise($request->query, $uid, 1);
  }

  /**
   * 接口： comment/unpraise
   * 取消赞主贴
   * @request module: 必选
   * @request mid: 必选
   *
   * @return list
   */
  public static function unpraise($request, $uid)
  {
    return CComment::praise($request->query, $uid, 0);
  }

}
