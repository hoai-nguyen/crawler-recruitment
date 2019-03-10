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

