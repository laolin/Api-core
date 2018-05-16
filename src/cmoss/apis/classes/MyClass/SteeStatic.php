<?php
// ================================
/*
 */
namespace MyClass;

class SteeStatic
{

  // 在几天之内查看的，允许直接再次查看而不用额度
  const MAX_REREAD_DAYS = 10;

  // 用到的表名
  static $table = [
    'steefac' => 'api_tbl_steelfactory',
    'steeproj' => 'api_tbl_steelproject',
    'stee_user' => 'api_tbl_stee_user',
    'token' => 'api_tbl_token',
    'log' => 'api_tbl_log',
    "wx" => 'api_tbl_user_wx',
    'user_right' => 'api_tbl_user_right', // 权限
    "user" => 'api_tbl_user',
  ];

  static $field = [
    'stee_user' => ['id', 'uid', 'name', 'is_admin', 'update_at', 'fac_can_admin', 'steefac_can_admin', 'steeproj_can_admin', 'rights', 'score'],
  ];

  static $fields_pre_detail = [
    'steefac' => [
      "id",
      "update_at",
      "level",
      "license",
      "name",
      "addr",
      "latE7",
      "lngE7",
      "province",
      "city",
      "district",
      "citycode",
      "adcode",
      "formatted_address",
      "contact_person",
      "cap_y",
      "cap_1m",
      "cap_2m",
      "cap_3m",
      "cap_6m",
      "workers",
      "workers_hangong",
      "workers_maogong",
      "workers_gongyi",
      "workers_xiangtu",
      "workers_other",
      "goodat",
      "fee",
      "area_factory",
      "area_duichang",
      "max_hangche",
      "max_paowan",
      "max_duxin",
      "dist_port",
      "dist_expressway",

      'contact_tel',
      'contact_email',

      "attr",
    ],
    'steeproj' => [
      "id",
      "update_at",
      "close_time",
      "name",
      "addr",
      "latE7",
      "lngE7",
      "province",
      "city",
      "district",
      "citycode",
      "adcode",
      "formatted_address",
      "size",
      "type",
      'steel_shape',
      'steel_Qxxx',
      'stee_price',
      'advance_pay',
      'provide_raw',
      'require_check',
      'contact_person',
      'notes',
      "in_month",
      "need_steel",

      'contact_tel',
      'contact_email',

      "attr",
    ],
  ];

  static $fields_preivew = [
    'steefac' => [
      'id',
      'mark',
      "update_at",
      "level",
      "name",
      "addr",
      "latE7",
      "lngE7",
      "cap_y",
      "cap_6m",
      "goodat",
      "area_factory",
    ],
    'steeproj' => [
      'id',
      'mark',
      "update_at",
      "close_time",
      "name",
      "addr",
      "latE7",
      "lngE7",
      'steel_shape',
      'steel_Qxxx',
      "in_month",
      "need_steel",
    ],
  ];
  //搜索查找 的字段，用在 /search API中
  static $fields_search = [
    'steefac' => [
      "id",
      "license",
      "name",
      "addr",
      "province",
      "city",
      "district",
      "citycode",
      "formatted_address",
      "goodat",
    ],
    'steeproj' => [
      'id',
      "name",
      "addr",
      "province",
      "city",
      "district",
      "citycode",
      "formatted_address",
      'steel_shape',
      'steel_Qxxx',
    ],
  ];

}
