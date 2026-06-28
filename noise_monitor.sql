/*
SQLyog Community v13.3.1 (64 bit)
MySQL - 10.4.32-MariaDB : Database - noise_monitor
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`noise_monitor` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `noise_monitor`;

/*Table structure for table `noise_incidents` */

DROP TABLE IF EXISTS `noise_incidents`;

CREATE TABLE `noise_incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sound_value` int(11) DEFAULT NULL,
  `percent_value` int(11) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `noise_incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `noise_incidents` */

/*Table structure for table `noise_readings` */

DROP TABLE IF EXISTS `noise_readings`;

CREATE TABLE `noise_readings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sound_value` int(11) DEFAULT NULL,
  `percent_value` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `threshold` int(11) DEFAULT NULL,
  `sensitivity` decimal(3,2) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `noise_readings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `noise_readings` */

/*Table structure for table `noise_stats` */

DROP TABLE IF EXISTS `noise_stats`;

CREATE TABLE `noise_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area` varchar(50) DEFAULT NULL,
  `total_readings` int(11) DEFAULT 0,
  `total_violations` int(11) DEFAULT 0,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_area_date` (`area`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `noise_stats` */

insert  into `noise_stats`(`id`,`area`,`total_readings`,`total_violations`,`date`) values 
(1,'reading',0,0,'2026-06-26'),
(2,'silent',0,0,'2026-06-26');

/*Table structure for table `user_activity` */

DROP TABLE IF EXISTS `user_activity`;

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `user_activity` */

insert  into `user_activity`(`id`,`user_id`,`action`,`details`,`ip_address`,`timestamp`) values 
(1,NULL,'login','User logged in','::1','2026-06-26 11:51:41'),
(2,NULL,'admin_make_admin','Promoted user ID: 2 to admin','::1','2026-06-26 11:51:56'),
(3,NULL,'admin_remove_admin','Removed admin from user ID: 2','::1','2026-06-26 11:51:59'),
(4,NULL,'admin_delete_user','Deleted user ID: 2','::1','2026-06-26 11:52:04'),
(5,NULL,'admin_delete_user','Deleted user ID: 1','::1','2026-06-26 11:52:06'),
(6,NULL,'admin_delete_user','Deleted user ID: 4','::1','2026-06-26 11:52:09'),
(7,NULL,'logout','User logged out','::1','2026-06-26 11:58:14'),
(8,NULL,'login','User logged in','::1','2026-06-26 11:58:46'),
(9,NULL,'logout','User logged out','::1','2026-06-26 12:09:12'),
(10,8,'login','User logged in','::1','2026-06-26 12:14:40'),
(11,8,'logout','User logged out','::1','2026-06-26 12:17:01'),
(12,8,'login','User logged in','::1','2026-06-26 12:18:22'),
(13,8,'logout','User logged out','::1','2026-06-26 12:20:49'),
(14,8,'login','User logged in','::1','2026-06-26 12:32:16'),
(15,8,'logout','User logged out','::1','2026-06-26 12:32:38'),
(16,8,'login','User logged in','::1','2026-06-26 12:41:53'),
(17,8,'logout','User logged out','::1','2026-06-26 12:41:56'),
(18,8,'login','User logged in','::1','2026-06-26 12:47:27'),
(19,8,'logout','User logged out','::1','2026-06-26 12:48:19'),
(20,8,'login','User logged in','::1','2026-06-26 12:54:35'),
(21,8,'logout','User logged out','::1','2026-06-26 12:54:44'),
(22,16,'login','User logged in','::1','2026-06-26 13:07:22'),
(23,16,'admin_make_admin','Promoted user ID: 8 to admin','::1','2026-06-26 13:08:56'),
(24,16,'admin_remove_admin','Removed admin from user ID: 8','::1','2026-06-26 13:08:59'),
(25,16,'admin_toggle_user','Toggled status for user ID: 8','::1','2026-06-26 13:09:00'),
(26,16,'admin_toggle_user','Toggled status for user ID: 8','::1','2026-06-26 13:09:03'),
(27,16,'admin_make_admin','Promoted user ID: 8 to admin','::1','2026-06-26 13:09:04'),
(28,16,'admin_remove_admin','Removed admin from user ID: 8','::1','2026-06-26 13:09:05'),
(29,16,'admin_make_admin','Promoted user ID: 8 to admin','::1','2026-06-26 13:13:15'),
(30,16,'logout','User logged out','::1','2026-06-26 13:13:19'),
(31,8,'login','User logged in','::1','2026-06-26 13:13:42'),
(32,8,'admin_remove_admin','Removed admin from user ID: 16','::1','2026-06-26 13:13:51'),
(33,8,'admin_make_admin','Promoted user ID: 16 to admin','::1','2026-06-26 13:13:52'),
(34,8,'admin_remove_admin','Removed admin from user ID: 16','::1','2026-06-26 13:13:54'),
(35,8,'admin_make_admin','Promoted user ID: 16 to admin','::1','2026-06-26 13:13:54'),
(36,8,'logout','User logged out','::1','2026-06-26 13:13:59');

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`username`,`email`,`password_hash`,`role`,`is_active`,`last_login`,`created_at`) values 
(8,'charles1','charlesdwayne.p.albano@isu.edu.ph','$2y$10$tOH10/k0EOzPPA6/YqKPRetgsfVO2Q1oaALTgvvpl7pF/qU.0E2si','admin',1,'2026-06-26 13:13:42','2026-06-26 12:11:44'),
(16,'superadmin1','shadow.cid.20061@gmail.com','$2y$10$5rDU6t45ADinmkTdljFhmuj7kqH3JMDnpdEJGKfosj8wi66E8pzYy','admin',1,'2026-06-26 13:07:22','2026-06-26 13:06:57');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
