<?php
//这个文件可以重定义，用来修改config
api_g("DBNAME",'test_01');
api_g("DBSERVER",'localhost');
api_g("DBPORT",3306);
api_g("DBUSER",'test_user');
api_g("DBPASS",'test_passwd');

api_g("path-wp",$_SERVER['DOCUMENT_ROOT'] . '/wordpress-cn');
api_g("WX_APPID",'');
api_g("WX_APPSEC",'');

//开头要有'/'，结束不能有'/'，从 index.php 所在路径相对计算
api_g("path-apis",[
    ['apis'=>['foot','mzapi'],'path'=>'/../../api-qgs-shops/src'],
    ['apis'=>['happy'],'path'=>'/../../api-bak']
  ]);

api_g('-cfg file--',__FILE__);
