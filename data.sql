-- phpMyAdmin SQL Dump
-- version 4.6.4
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 2017-05-19 00:08:32
-- 服务器版本： 5.5.27
-- PHP Version: 5.6.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `api_01`
--

-- --------------------------------------------------------

--
-- 表的结构 `api_tbl_log`
--

CREATE TABLE `api_tbl_log` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `api` varchar(1023) NOT NULL,
  `host` varchar(127) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cur_time` int(11) DEFAULT NULL,
  `get` text,
  `post` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `api_tbl_token`
--

CREATE TABLE `api_tbl_token` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `tokenid` varchar(64) NOT NULL,
  `token` varchar(128) NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `tokenDesc` varchar(255) DEFAULT '''''',
  `tokenTime` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastTime` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `api_tbl_tokenbucket`
--

CREATE TABLE `api_tbl_tokenbucket` (
  `id` int(11) NOT NULL,
  `user` varchar(255) NOT NULL,
  `capacity` int(11) NOT NULL,
  `tokens` int(11) NOT NULL,
  `fillRate` decimal(8,3) NOT NULL,
  `lastRun` int(11) NOT NULL,
  `userGroup` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `api_tbl_uploads`
--

CREATE TABLE `api_tbl_uploads` (
  `fid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `fdesc` varchar(255) COLLATE utf8_bin NOT NULL,
  `fname` varchar(255) COLLATE utf8_bin NOT NULL,
  `oname` varchar(255) COLLATE utf8_bin NOT NULL,
  `ftype` varchar(32) COLLATE utf8_bin NOT NULL,
  `ftime` int(11) NOT NULL,
  `fsize` int(11) NOT NULL,
  `ffrom` varchar(32) COLLATE utf8_bin DEFAULT NULL,
  `tmstamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `count` int(11) NOT NULL DEFAULT '0',
  `mark` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- 表的结构 `api_tbl_user`
--

CREATE TABLE `api_tbl_user` (
  `uid` int(11) UNSIGNED NOT NULL,
  `uname` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upass` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `regtime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rights` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `api_tbl_user_wx`
--

CREATE TABLE `api_tbl_user_wx` (
  `id` int(11) NOT NULL,
  `appFrom` varchar(32) DEFAULT NULL,
  `uidBinded` int(11) NOT NULL DEFAULT '0',
  `openid` varchar(32) NOT NULL,
  `unionid` varchar(32) DEFAULT NULL,
  `nickname` varchar(255) DEFAULT NULL,
  `sex` varchar(2) DEFAULT NULL,
  `language` varchar(16) DEFAULT NULL,
  `city` varchar(32) DEFAULT NULL,
  `province` varchar(32) DEFAULT NULL,
  `country` varchar(32) DEFAULT NULL,
  `headimgurl` varchar(255) DEFAULT NULL,
  `lastUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_tbl_log`
--
ALTER TABLE `api_tbl_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_tbl_token`
--
ALTER TABLE `api_tbl_token`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_tbl_tokenbucket`
--
ALTER TABLE `api_tbl_tokenbucket`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `api_tbl_uploads`
--
ALTER TABLE `api_tbl_uploads`
  ADD PRIMARY KEY (`fid`);

--
-- Indexes for table `api_tbl_user`
--
ALTER TABLE `api_tbl_user`
  ADD PRIMARY KEY (`uid`);

--
-- Indexes for table `api_tbl_user_wx`
--
ALTER TABLE `api_tbl_user_wx`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `api_tbl_log`
--
ALTER TABLE `api_tbl_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- 使用表AUTO_INCREMENT `api_tbl_token`
--
ALTER TABLE `api_tbl_token`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- 使用表AUTO_INCREMENT `api_tbl_tokenbucket`
--
ALTER TABLE `api_tbl_tokenbucket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- 使用表AUTO_INCREMENT `api_tbl_uploads`
--
ALTER TABLE `api_tbl_uploads`
  MODIFY `fid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
--
-- 使用表AUTO_INCREMENT `api_tbl_user`
--
ALTER TABLE `api_tbl_user`
  MODIFY `uid` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10000;
--
-- 使用表AUTO_INCREMENT `api_tbl_user_wx`
--
ALTER TABLE `api_tbl_user_wx`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
