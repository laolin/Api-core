

-- 用户
create table if not exists `z_user` 
(
  `uid`            int(11)  not null auto_increment COMMENT  '用户id',
  `nick`           varchar(64) DEFAULT '' COMMENT  '呢称',
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
  `rqupdate`       varchar(16)  COMMENT '最后更新资料时间'                                                                  ,
  primary key (`id`),
  unique key `id` (`id`),
  key `openid` (`openid`)
);


