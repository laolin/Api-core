<?php
//这个文件可以重定义，用来修改config

api_g('api_cfg_ver','3.00');


/*******************************************************************
 *  定义数据库
 */
api_g("DBNAME",'test_dbname');
api_g("DBSERVER",'localhost');
api_g("DBPORT",3306);
api_g("DBUSER",'test_username');
api_g("DBPASS",'test_password');

/******************************************************************
 *  定义数据表名
 *
 *  api_tbl_log 访问记录
 *  api_tbl_tokenbucket 令牌桶，用于控制访问量
 *  api_tbl_user
 *  api_tbl_token
 *  api_tbl_wxuser
 *  api_tbl_uploads 上传文件记录
 */
api_g("api-table-prefix",'api_tbl_');
api_g("usr-salt",['version'=>'ab','salt'=>'api_salt-laolin@&*']); //目前 version 长度 要求==2

//默认用户权限
api_g("user_default_rights",1);

/*******************************************************************
 *  重定义 apis 路径用的
 */
//开头要有'/'，结束不能有'/'，从 index.php 所在路径相对计算
api_g("path-apis",[
    ['apis'=>['foot','mzapi'],'path'=>'/../../api-qgs-shops/src/apis'],
  ]);


/*******************************************************************
 *  令牌桶 设置. class.token.bucket.php 用的。
 */
api_g("api_bucket",[
  'anony_cost'=>10,// 匿名用户一次消耗的令牌数（登录的用户一次消耗一个令牌）
  'capacity'=>100, // 令牌桶内最多令牌个数
  'fillrate'=>1 // 2表示每秒加2个令牌 , 0.1表示10 秒加 1 个令牌
]);


/*******************************************************************
 *  WX-APPS 定义
 */
api_g("WX_APPS", [
  'qgs-web'=>
    ['wx-app-id 1','wx-app-secret xx1'],
  'qgs-mp'=>
    ['wx-app-id 2','xx2'],
  'laolin-mp'=>
    ['wx-app-id 3','xx3']
]);
 

/*******************************************************************
 *  定义 uploads API 接收到的上传文件存放处
 */
//开头要有'/'，结束不能有'/'，从 index.php 所在路径相对计算
api_g("upload-settings",[
  'path'=>'/uploads-for-api',
  //访问WEB服务器'path-pub'路径，这个应该指向'path'同一地方，
  //'path-pub'可以是path的软链接
  //'path-pub'也可以通过rewrite重定向到'path'
  'path-pub'=>'/uploads-for-api',
  'ext'=>'pdf,jpg,png,gif,tif,tiff,bmp,dwg,zip,rar,txt'
]);


// END, cfg mark 
api_g('-cfg file--',__FILE__);

//本地调试用的 服务器上删除下面两行
if(file_exists('../../api-bak/index.config__test__.php'))
  require_once '../../api-bak/index.config__test__.php';
