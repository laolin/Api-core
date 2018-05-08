<?php
namespace NApiUnit;

/**
 * 评论类
 */
class CComment
{
  //static $table = CDbBase::table("comment");
  static $field = [
    'detail' => ['id', 'uid', 'type', 'cid', 'attr', 't_update'],
  ];

  /**
   * 列出指定模块和mid所有主贴
   * @query module: 必选
   * @query mid: 必选
   * @query limit: 可选, 列表范围, 1~2个数字, 同SQL
   *
   * @return list
   */
  public static function li($query, $uid)
  {
    $AND = [
      'del[!]' => '1',
      'module' => $query['module'],
      'mid' => $query['mid'],
    ];
    $db = CDbBase::db();
    $list = $db->select(CDbBase::table("comment"), self::$field['detail'], ['AND' => $AND]);
    \DJApi\API::debug(['list' => $list, 'DB' => $db->getShow()]);

    if (is_array($list)) {
      foreach ($list as $k => $row) {
        $list[$k]['attr'] = json_decode($row['attr'], true);
      }
    }

    // 现在可能有多余的主贴已删除的跟帖和点赞
    $list_publish = array_filter($list, function ($item) {
      return $item['type'] == 'publish';
    });
    $list_publish_ids = array_map(function ($item) {return $item['id'];}, $list_publish);
    $list = array_filter($list, function ($item) use ($list_publish_ids) {
      return $item['type'] == 'publish' || in_array($item['cid'], $list_publish_ids);
    });

    return \DJApi\API::OK(['list' => array_values($list)]);
  }

  /**
   * 删除一条主贴
   * @query module: 必选
   * @query mid: 必选
   * @query cid: 要删除的评论
   *
   * @return []
   */
  public static function remove($query, $uid)
  {
    $db = CDbBase::db();
    $AND = [
      'del[!]' => '1',
      'id' => $query['id'],
      'module' => $query['module'],
      'mid' => $query['mid'],
    ];
    $row = $db->get(CDbBase::table("comment"), ['id', 'uid'], ['AND' => $AND]);
    if (!is_array($row)) {
      \DJApi\API::debug(['row' => $row, 'DB' => $db->getShow()]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "无主贴");
    }
    if ($row['uid'] != $uid) {
      \DJApi\API::debug(['row' => $row, 'DB' => $db->getShow()]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "只能删除本人的帖子");
    }
    $n = $db->update(CDbBase::table("comment"), ['del' => '1'], ['id' => $query['id']]);
    return \DJApi\API::OK(['n' => $n]);
  }

  /**
   * 添加一条主贴
   * @query module: 必选
   * @query mid: 必选
   * @query data: 评论数据
   *
   * @return list
   */
  public static function add($query, $uid)
  {
    $db = CDbBase::db();

    /** 数据验证 */
    $content = trim($query['data']['content']);
    // if (mb_strlen($content, 'utf8') < 2) {
    //   return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "至少2个字", [$content, mb_strlen($content, 'utf8')]);
    // }

    /** 主数据 */
    $attr = $query['data'];
    $t_update = time();

    /** 写入数据库 */
    $insert_id = $db->insert(CDbBase::table("comment"), [
      'module' => $query['module'],
      'mid' => $query['mid'],
      'cid' => '',
      'type' => 'publish',
      'uid' => $uid,
      'attr' => \DJApi\API::cn_json($attr),
      't_update' => $t_update,
    ]);
    \DJApi\API::debug(['新帖, DB' => $db->getShow()]);

    return \DJApi\API::OK(['id' => $insert_id, 't_update' => $t_update]);
  }

  /**
   * 回复一条评论
   * @query module: 必选
   * @query mid: 必选
   * @query fuid: 要回复的用户
   * @query content: 要回复的内容
   *
   * @return list
   */
  public static function feedback($query, $uid)
  {
    $AND = [
      'id' => $query['cid'],
      'module' => $query['module'],
      'mid' => $query['mid'],
    ];
    $db = CDbBase::db();
    $row = $db->get(CDbBase::table("comment"), ['id'], ['AND' => $AND]);
    if (!is_array($row)) {
      \DJApi\API::debug(['row' => $row, 'DB' => $db->getShow()]);
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "无主贴");
    }

    /** 数据验证 */
    $content = trim($query['content']);
    if (mb_strlen($content, 'utf8') < 2) {
      return \DJApi\API::error(\DJApi\API::E_PARAM_ERROR, "至少2个字", [$content, mb_strlen($content, 'utf8')]);
    }

    $attr = [
      'content' => $content,
      'fuid' => $query['fuid'],
    ];
    $db->insert(CDbBase::table("comment"), [
      'module' => $query['module'],
      'mid' => $query['mid'],
      'cid' => $query['cid'],
      'type' => 'feedback',
      'uid' => $uid,
      'attr' => \DJApi\API::cn_json($attr),
      't_update' => time(),
    ]);
    \DJApi\API::debug(['新回帖, DB' => $db->getShow()]);

    return \DJApi\API::OK([]);
  }

  /**
   * 删除一条回复
   * @query module: 必选
   * @query mid: 必选
   * @query cid: 回复的id
   *
   * @return list
   */
  public static function removeFeedback($query, $uid)
  {
  }

  /**
   * 赞/取消赞主贴
   * @query module: 必选
   * @query mid: 必选
   * @query cid: 必选
   * @param yn: 1=赞, 非1=取消
   *
   * @return praise: 1=已赞, 0=已取消
   */
  public static function praise($query, $uid, $yn)
  {
    $yn = $yn == 1 ? 1 : 0;
    $AND = [
      'module' => $query['module'],
      'mid' => $query['mid'],
      'cid' => $query['cid'],
      'type' => 'praise',
      'uid' => $uid,
    ];
    $db = CDbBase::db();
    $row = $db->get(CDbBase::table("comment"), ['id', 'del'], ['AND' => $AND]);
    \DJApi\API::debug(['row' => $row, 'DB' => $db->getShow()]);

    if (is_array($row)) {
      $praised = $row['del'] == 1 ? 0 : 1;
      if ($praised != $yn) {
        $db->update(CDbBase::table("comment"), ['del' => 1 - $yn, 't_update' => time()], ['id' => $row['id']]);
      }
      \DJApi\API::debug(['更新, praised=' => $praised, ', yn=' => $yn, 'DB' => $db->getShow()]);
    } else {
      $db->insert(CDbBase::table("comment"), [
        'module' => $query['module'],
        'mid' => $query['mid'],
        'cid' => $query['cid'],
        'type' => 'praise',
        'uid' => $uid,
        'del' => 1 - $yn,
        't_update' => time(),
      ]);
      \DJApi\API::debug(['新点赞, DB' => $db->getShow()]);
    }

    return \DJApi\API::OK(['praise' => $yn]);
  }
}
