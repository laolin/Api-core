

-- 主贴表
-- drop table if exists `unit_comment`;
create table if not exists `unit_comment`
(
  `id`          int(11)  not null auto_increment COMMENT  'id',
  `module`      varchar(32) DEFAULT '' COMMENT  '模块名',
  `mid`         varchar(16) DEFAULT '' COMMENT  '在某模块中，用于区别多贴的id',
  `uid`         varchar(16) DEFAULT '' COMMENT  '用户id',
  `type`        varchar(16) DEFAULT '' COMMENT  '发贴/跟帖/点赞',
  `cid`         varchar(16) DEFAULT '' COMMENT  '跟帖/点赞时，主贴id',
  `del`         varchar( 2) DEFAULT '' COMMENT  '删除标志，1表示已删除',
  `attr`        text  COMMENT '所有数据，包括修改记录',
  `t_update`    varchar(32) DEFAULT '' COMMENT  '最后更新时间',
  primary key (`id`),
  unique key `id` (`id`)
)default charset=utf8;
