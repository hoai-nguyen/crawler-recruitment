CREATE DATABASE IF NOT EXISTS phpmyadmin;

use phpmyadmin;

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`job_metadata`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`job_name` varchar(200) DEFAULT NULL,
	`start_page` int(11) DEFAULT NULL,
	`end_page` int(11) DEFAULT NULL,
	`timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`topdev`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`topcv`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`itviec`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`vieclam24h`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`mywork`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_uv_mywork`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`timviecnhanh`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`careerlink`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`findjobs`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`careerbuilder`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_laodong`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_uv_laodong`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_timviec365`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_tuyencongnhan`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_tuyendungsinhvien`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_uv_tuyendungsinhvien`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_tuyendungcomvn`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_uv_tuyendungcomvn`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_uv_kenhtimviec`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_itguru`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_tenshoku`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_tenshokuex`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_hatalike`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_rikunabi`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_doda`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `phpmyadmin`.`crawler_enjapan`(
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`link` varchar(2000) DEFAULT NULL,
	PRIMARY KEY(`id`)
);

CREATE TABLE `phpmyadmin`.`job_file_index` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_index` int(11) NOT NULL,
  `job_name` varchar(200) COLLATE utf8_bin DEFAULT NULL,
  `status` varchar(20) COLLATE utf8_bin DEFAULT NULL,
  `running_instance` int(11) DEFAULT 0,
  `created_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
