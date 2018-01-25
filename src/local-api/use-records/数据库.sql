--
-- 表的结构 `api_tbl_use_record`
--

CREATE TABLE `api_tbl_use_record` (
  `id`   int(11) not null auto_increment,
  `module` varchar( 32) DEFAULT '默认模块' COMMENT  '模块名字',
  `uid`  int(11) NOT NULL COMMENT '用户id',
  `time` varchar( 32) DEFAULT '' COMMENT  '时间',
  `k1`   varchar( 32) DEFAULT '' COMMENT  '主分类',
  `k2`   varchar( 32) DEFAULT '' COMMENT  '次分类',
  `v1`   varchar(128) DEFAULT '' COMMENT  '主值',
  `v2`   varchar(128) DEFAULT '' COMMENT  '次值',
  `n`    varchar( 11) DEFAULT '' COMMENT  '数量',
  `json` text COMMENT  '使用的相关信息',
  primary key (`id`),
  unique key `id` (`id`)
) DEFAULT CHARSET=utf8;
