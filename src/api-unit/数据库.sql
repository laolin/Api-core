
-- 将正式库复制到测试库
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_comment     ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_feed          ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_steelfactory  ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_steelproject  ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_stee_user     ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_token        ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_tokenbucket   ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_uploads        ;
TRUNCATE TABLE `cmoss-fac-test`.api_tbl_use_record    ;

insert into `cmoss-fac-test`.api_tbl_comment        select * from `cmoss-fac`.api_tbl_comment      ;
insert into `cmoss-fac-test`.api_tbl_feed           select * from `cmoss-fac`.api_tbl_feed         ;
insert into `cmoss-fac-test`.api_tbl_steelfactory   select * from `cmoss-fac`.api_tbl_steelfactory ;
insert into `cmoss-fac-test`.api_tbl_steelproject   select * from `cmoss-fac`.api_tbl_steelproject ;
insert into `cmoss-fac-test`.api_tbl_stee_user      select * from `cmoss-fac`.api_tbl_stee_user    ;
insert into `cmoss-fac-test`.api_tbl_token          select * from `cmoss-fac`.api_tbl_token        ;
insert into `cmoss-fac-test`.api_tbl_tokenbucket    select * from `cmoss-fac`.api_tbl_tokenbucket  ;
insert into `cmoss-fac-test`.api_tbl_uploads        select * from `cmoss-fac`.api_tbl_uploads      ;
insert into `cmoss-fac-test`.api_tbl_use_record     select * from `cmoss-fac`.api_tbl_use_record   ;









-- 从旧数据库中同步

-- step1 从原正式库中备份数据
--
-- 表: api_tbl_user, api_tbl_user_wx


-- step2 将备份的数据复制到测试库
--
-- 直接运行备份的 sql

-- step3 清空测试库

-- TRUNCATE TABLE `qgs-user-test`.z_user;
-- TRUNCATE TABLE `qgs-user-test`.z_user_token;
-- TRUNCATE TABLE `qgs-user-test`.z_user_bind;
-- TRUNCATE TABLE `qgs-user-test`.z_wx_user;

-- step4 转换数据
-- step4.1 转换 z_user 数据
TRUNCATE TABLE `qgs-user-test`.z_user;
insert into `qgs-user-test`.z_user (uid, `name`, `password`)
  select
    uid, uname, upass
  from `qgs-user-test`.api_tbl_user;
-- step4.2 转换 z_user_bind openid 数据;
TRUNCATE TABLE `qgs-user-test`.z_user_bind;
insert into `qgs-user-test`.z_user_bind (uid, param1, `value`, bindtype)
  select
    uidBinded,
    appFrom,
    openid,
    left('wx-openid', 20)
  from `qgs-user-test`.api_tbl_user_wx WHERE length(openid)=28 AND length(unionid)=28;
-- step4.2 转换 z_user_bind unionid 数据
insert into `qgs-user-test`.z_user_bind (uid, param1, `value`, bindtype)
  select
    uidBinded,
    appFrom,
    unionid,
    left('wx-unionid', 20)
  from `qgs-user-test`.api_tbl_user_wx WHERE length(openid)=28 AND length(unionid)=28;

-- step4.4 转换微信数据到 z_wx_user 表
TRUNCATE TABLE `qgs-user-test`.z_wx_user;
insert into `qgs-user-test`.z_wx_user (
    `openid`        ,
    `nickname`      ,
    `headimgurl`    ,
    `sex`           ,
    `language`      ,
    `province`      ,
    `city`          ,
    `country`       ,
    `unionid`       ,
    `subscribe`     ,
    `subscribe_time`,
    `groupid`
  )
  select
    `openid`        ,
    `nickname`      ,
    `headimgurl`    ,
    `sex`           ,
    `language`      ,
    `province`      ,
    `city`          ,
    `country`       ,
    `unionid`       ,
    `subscribe`     ,
    `subscribe_time`,
    `groupid`
 from `qgs-user-test`.api_tbl_user_wx WHERE length(openid)=28 AND length(unionid)=28;



-- 用户活跃度分段索引
-- drop table if exists `api_tbl_log_time`;
create table if not exists `api_tbl_log_time` 
(
  `id`        int(11),
  `time`      timestamp,
  `cur_time`  int(11),
  primary key (`id`),
  unique key `id` (`id`)
)default charset=utf8;

-- MySql 后台每天运行：
-- insert into api_tbl_log_time (id,time, cur_time)
--   SELECT `id` ,`time` ,`cur_time`   FROM `api_tbl_log`,
--   ( SELECT max(`cur_time` + 3600*24) as mm FROM `api_tbl_log_time`) tmp
--   WHERE `cur_time`  >tmp.mm
--   GROUP BY left(time,10);




-- 用户
drop table if exists `z_user`;
create table if not exists `z_user` 
(
  `uid`            int(11)  not null auto_increment COMMENT  '用户id',
  `nick`           varchar(64) DEFAULT '' COMMENT  '呢称',
  `name`           varchar(64) DEFAULT '' COMMENT  '姓名',
  `password`       varchar(64) DEFAULT '' COMMENT  '密码',
  primary key (`uid`),
  unique key `uid` (`uid`)
)default charset=utf8 auto_increment=300000 ;

-- 用户票据
create table if not exists `z_user_token` 
(
  `tokenid`        int(11)  not null auto_increment,
  `uid`            varchar(16) COMMENT '用户id',
  `token`          varchar(64) DEFAULT '',
  `token_time`     varchar(32) DEFAULT '',
  primary key (`tokenid`),
  unique key `tokenid` (`tokenid`)
)default charset=utf8;


-- 用户绑定，尚未启用
create table if not exists `z_user_bind` 
(
  `id`             int(11)  not null auto_increment COMMENT  'id',
  `uid`            varchar(16) COMMENT '用户id',
  `bindtype`       varchar(16) DEFAULT 0  COMMENT  '绑定方式',
  `param1`         varchar(64) DEFAULT '' COMMENT  '绑定参数1',
  `param2`         varchar(64) DEFAULT '' COMMENT  '绑定参数2',
  `value`          varchar(64) DEFAULT '' COMMENT  '绑定值',
  primary key (`id`),
  unique key `id` (`id`)
)default charset=utf8;


create table if not exists `z_wx_user` 
(
  `id`             int(11)  not null auto_increment ,
  `openid`         varchar(64)  COMMENT '微信openid'                                                                        ,
  `nickname`       varchar(64)  COMMENT '微信呢称'                                                                          ,
  `headimgurl`     varchar(256) COMMENT '微信头像链接'                                                                      ,
  `sex`            varchar(64)  COMMENT '用户的性别，值为1时是男性，值为2时是女性，值为0时是未知'                           ,
  `language`       varchar(64)  COMMENT '用户语言'                                                                          ,
  `province`       varchar(64)  COMMENT '用户所在省份'                                                                      ,
  `city`           varchar(64)  COMMENT '用户所在城市'                                                                      ,
  `country`        varchar(64)  COMMENT '用户所在国家'                                                                      ,
  `unionid`        varchar(64)  COMMENT '只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段。'                      ,
  `subscribe`      varchar(64)  COMMENT '用户是否订阅该公众号标识，值为0时，代表此用户没有关注该公众号，拉取不到其余信息。' ,
  `subscribe_time` varchar(64)  COMMENT '用户关注时间，为时间戳。如果用户曾多次关注，则取最后关注时间'                      ,
  `remark`         varchar(64)  COMMENT '公众号运营者对粉丝的备注，公众号运营者可在微信公众平台用户管理界面对粉丝添加备注'  ,
  `groupid`        varchar(64)  COMMENT '用户所在的分组ID'                                                                  ,
  `timeupdate`       varchar(16)  COMMENT '最后更新资料时间'                                                                  ,
  primary key (`id`),
  unique key `id` (`id`),
  key `openid` (`openid`)
);


