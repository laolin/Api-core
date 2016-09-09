<?php
//这个文件可以重定义，用来修改config
api_g("DBNAME",'test_01');
api_g("DBSERVER",'localhost');
api_g("DBPORT",3306);
api_g("DBUSER",'test_user');
api_g("DBPASS",'test_passwd');

api_g('--mark--','000-index.config.php--db @ src');
