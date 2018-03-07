<?php
// ================================
/*
*/
namespace MyClass;

class SteeStatic{

  // 用到的表名
  static $table = [
    'steefac'   => 'api_tbl_steelfactory',
    'steeproj'  => 'api_tbl_steelproject',
    'stee_user' => 'api_tbl_stee_user',
    'token'     => 'api_tbl_token',
    'log'       => 'api_tbl_log',
    "wx"        => 'api_tbl_user_wx',
    "user"      => 'api_tbl_user',
  ];

  // 在几天之内查看的，允许直接再次查看而不用额度
  const MAX_REREAD_DAYS = 10;
}
