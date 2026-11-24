-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: onbolt_management
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` enum('Admin','Parent','Visitor','System') NOT NULL DEFAULT 'System',
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `module` varchar(80) NOT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_act_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:28:01'),(2,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:28:17'),(3,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:28:32'),(4,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:28:48'),(5,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:29:09'),(6,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:40:32'),(7,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 06:57:32'),(8,'Parent',1,'Parent Login','auth',NULL,'Parent signed in to Trivandrum International School with ID: TIS202601','::1','2026-11-18 07:19:04');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_schools`
--

DROP TABLE IF EXISTS `admin_schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_schools` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `school_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_school` (`admin_id`,`school_id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_schools`
--

LOCK TABLES `admin_schools` WRITE;
/*!40000 ALTER TABLE `admin_schools` DISABLE KEYS */;
INSERT INTO `admin_schools` VALUES (1,5,28,'2026-11-24 09:33:14'),(2,6,29,'2026-11-24 09:33:14');
/*!40000 ALTER TABLE `admin_schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL DEFAULT 1,
  `school_id` int(10) unsigned DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_role` (`role_id`),
  KEY `idx_admins_school` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,1,NULL,'admin','OnBolt Super Administrator','info@onbolt.in','$2y$10$ILQBnfkc5RkJOtpZzTJJYeqUs.2..lGEKX1kS6Frh0hoIkmR1F5eW',1,'2026-11-24 14:55:33','2026-11-18 06:20:35','2026-11-24 09:25:33'),(3,4,NULL,'softadmin','Software Admin Operations','softadmin@onbolt.in','$2y$10$29NnQhKW8Qj4wTphKXk2M.E7kXV0eKdPRbHTf2Y26lQ5ZAcv4G/1.',1,'2026-11-23 11:41:13','2026-11-23 05:40:33','2026-11-23 06:11:13'),(4,5,NULL,'softuser','Software User / Staff','softuser@onbolt.in','$2y$10$29NnQhKW8Qj4wTphKXk2M.E7kXV0eKdPRbHTf2Y26lQ5ZAcv4G/1.',1,'2026-11-23 11:40:10','2026-11-23 05:40:33','2026-11-23 06:10:10'),(5,6,28,'school_tvm','TVM Main School Desk','tvm_main@onbolt.in','$2y$10$29NnQhKW8Qj4wTphKXk2M.E7kXV0eKdPRbHTf2Y26lQ5ZAcv4G/1.',1,NULL,'2026-11-23 05:40:33','2026-11-23 05:40:33'),(6,6,29,'school_kowdiar','TVM Kowdiar School Desk','kowdiar@onbolt.in','$2y$10$29NnQhKW8Qj4wTphKXk2M.E7kXV0eKdPRbHTf2Y26lQ5ZAcv4G/1.',1,NULL,'2026-11-23 05:40:33','2026-11-23 05:40:33');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_settings`
--

DROP TABLE IF EXISTS `application_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_set_key` (`setting_key`),
  KEY `idx_app_set_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_settings`
--

LOCK TABLES `application_settings` WRITE;
/*!40000 ALTER TABLE `application_settings` DISABLE KEYS */;
INSERT INTO `application_settings` VALUES (1,'site_name','OnBolt','general','Company Brand Name','2026-11-18 06:20:35'),(2,'site_tagline','Complete Branding Solutions','general','Main Brand Tagline','2026-11-18 10:04:00'),(3,'site_logo','assets/images/logo/onbolt-actual-logo.png','general','Primary Header Logo','2026-11-18 10:04:00'),(4,'site_logo_light','assets/images/logo/onbolt-logo-white.png','general','Light Variant Logo','2026-11-18 10:19:35'),(5,'site_favicon','assets/images/logo/cropped-favicon-1-96x96.png','general','Browser Favicon','2026-11-18 06:20:35'),(6,'contact_email','onboltconsumerindia@gmail.com','contact','Public Support Email','2026-11-18 06:20:35'),(7,'contact_phone','+91 9946925888','contact','Customer Hotline Phone','2026-11-18 06:20:35'),(8,'whatsapp_order_phone','918848670739','orders','Default WhatsApp recipient phone for school kit orders','2026-11-18 06:20:35'),(9,'office_address','3rd Floor, Rema Plaza, S.S Kovil Road, Thampanoor, Trivandrum û 695001','contact','Head Office Address','2026-11-19 04:53:11'),(10,'currency_symbol','₹','ecommerce','Default Currency Symbol','2026-11-18 06:20:35'),(11,'orders_notification_email','orders@onbolt.in','orders','Alert Email for New Orders','2026-11-18 06:20:35');
/*!40000 ALTER TABLE `application_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_slug` varchar(80) NOT NULL,
  `title` varchar(180) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `button_text` varchar(80) DEFAULT NULL,
  `button_link` varchar(200) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_banners_page` (`page_slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
INSERT INTO `banners` VALUES (1,'about','About OnBolt','Crafting Identity Since Inception','assets/images/banners/a1-bg-img-1.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31'),(2,'categories','Product Categories','Comprehensive Uniform & Merchandise Spectrum','assets/images/banners/p3-bg-img.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31'),(3,'portfolio','Our Portfolio','Featured Projects & Partner Institutions','assets/images/banners/p3-bg-img.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31'),(4,'schools','Partner Schools','Select Your School For Tailored Uniform Ordering','assets/images/banners/a1-bg-img-1.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31'),(5,'customised-gifts','Customised Gifts','Thoughtful Personalised Products','assets/images/banners/p3-bg-img.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31'),(6,'corporate-tshirts','Corporate T-Shirts','Premium Business Activewear','assets/images/banners/a1-bg-img-1.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31'),(7,'contact','Contact Us','We Would Love To Hear From You','assets/images/banners/p3-bg-img.webp',NULL,NULL,0,1,'2026-11-18 06:21:31','2026-11-18 06:21:31');
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `size` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ci_cart` (`cart_id`),
  CONSTRAINT `fk_ci_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
INSERT INTO `cart_items` VALUES (9,2,1,NULL,'32',2,399.00,'2026-11-18 07:15:29','2026-11-18 07:15:29'),(10,3,1,NULL,'32',2,399.00,'2026-11-18 07:15:53','2026-11-18 07:15:53');
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `session_id` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_carts_school` (`school_id`),
  KEY `idx_carts_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
INSERT INTO `carts` VALUES (1,1,1,'2pii5li111i86d78frjv86m0g8','2026-11-18 06:28:01','2026-11-18 06:28:01'),(2,1,1,'v9lki6621s7f9qbdghpqr5o6np','2026-11-18 07:15:29','2026-11-18 07:15:29'),(3,1,1,'6e2piiinihlmcjlj87ua65nf75','2026-11-18 07:15:53','2026-11-18 07:15:53'),(4,1,1,'u7i5a1hhllgq3h2kerjg8p28dv','2026-11-18 07:18:55','2026-11-18 07:18:55');
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!50001 DROP VIEW IF EXISTS `categories`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `categories` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `slug`,
  1 AS `description`,
  1 AS `image`,
  1 AS `icon`,
  1 AS `type`,
  1 AS `sort_order`,
  1 AS `status`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `contact_enquiries`
--

DROP TABLE IF EXISTS `contact_enquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_enquiries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(180) DEFAULT NULL,
  `message` text NOT NULL,
  `school_name` varchar(150) DEFAULT NULL,
  `status` enum('New','In Progress','Resolved','Archived') NOT NULL DEFAULT 'New',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_enquiries`
--

LOCK TABLES `contact_enquiries` WRITE;
/*!40000 ALTER TABLE `contact_enquiries` DISABLE KEYS */;
INSERT INTO `contact_enquiries` VALUES (1,'Adarsh Test','test@onbolt.com','9876543210','Uniform Inquiry','Automated test',NULL,'New',NULL,'2026-11-18 10:20:21');
/*!40000 ALTER TABLE `contact_enquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `counter_collections`
--

DROP TABLE IF EXISTS `counter_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `counter_collections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `school_id` int(10) unsigned NOT NULL,
  `sub_school_id` int(10) unsigned DEFAULT NULL,
  `handover_staff` varchar(100) NOT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `recipient_phone` varchar(50) DEFAULT NULL,
  `confirmation_code` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `collected_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `school_id` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `counter_collections`
--

LOCK TABLES `counter_collections` WRITE;
/*!40000 ALTER TABLE `counter_collections` DISABLE KEYS */;
INSERT INTO `counter_collections` VALUES (1,19,1,NULL,'RS','MANIKANDAN V','08778979416','COD-4193','Collected directly at school counter','2026-11-23 11:39:33','2026-11-23 06:09:33');
/*!40000 ALTER TABLE `counter_collections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `homepage_sections`
--

DROP TABLE IF EXISTS `homepage_sections`;
/*!50001 DROP VIEW IF EXISTS `homepage_sections`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `homepage_sections` AS SELECT
 1 AS `id`,
  1 AS `section_group`,
  1 AS `section_key`,
  1 AS `label`,
  1 AS `subtitle`,
  1 AS `title`,
  1 AS `description`,
  1 AS `image`,
  1 AS `icon`,
  1 AS `button_text`,
  1 AS `button_link`,
  1 AS `sort_order`,
  1 AS `status`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `current_stock` int(11) NOT NULL DEFAULT 100,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `reserved_stock` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inv_product` (`product_id`),
  KEY `idx_inv_variant` (`variant_id`),
  CONSTRAINT `fk_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `navigation`
--

DROP TABLE IF EXISTS `navigation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `navigation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `label` varchar(80) NOT NULL,
  `url` varchar(255) NOT NULL,
  `target` varchar(20) NOT NULL DEFAULT '_self',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nav_parent` (`parent_id`),
  KEY `idx_nav_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `navigation`
--

LOCK TABLES `navigation` WRITE;
/*!40000 ALTER TABLE `navigation` DISABLE KEYS */;
INSERT INTO `navigation` VALUES (1,NULL,'Home','index.php','_self',1,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(2,NULL,'About','about.php','_self',2,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(3,NULL,'Categories','categories.php','_self',3,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(4,NULL,'Portfolio','portfolio.php','_self',4,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(5,NULL,'Schools','schools/','_self',5,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(6,NULL,'Customised Gifts','customised-gift.php','_self',6,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(7,NULL,'Corporate T-Shirts','corporate-tshirt.php','_self',7,1,'2026-11-18 06:23:43','2026-11-18 06:23:43'),(8,NULL,'Contact Us','contact-us.php','_self',8,1,'2026-11-18 06:23:43','2026-11-18 06:23:43');
/*!40000 ALTER TABLE `navigation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_subscribers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `status` enum('Subscribed','Unsubscribed') NOT NULL DEFAULT 'Subscribed',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
INSERT INTO `newsletter_subscribers` VALUES (1,'parent123@gmail.com','Subscribed',NULL,'2026-11-18 10:20:26');
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT 'order',
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `school_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'order','New School Kit Order','New order OB-09B0E4 placed by Rajesh Kumar for Aarav Kumar (Trivandrum International School)','orders.php?order_ref=OB-09B0E4',0,1,'2026-11-18 06:28:48'),(2,'order','New School Kit Order','New order OB-5BD79D placed by Rajesh Kumar for Aarav Kumar (Trivandrum International School)','orders.php?order_ref=OB-5BD79D',0,1,'2026-11-18 06:29:09'),(3,'order','New School Kit Order','New order OB-049021 placed by Rajesh Kumar for Aarav Kumar (Trivandrum International School)','orders.php?order_ref=OB-049021',0,1,'2026-11-18 06:40:32'),(4,'order','New School Kit Order','New order OB-CC3D3E placed by Rajesh Kumar for Aarav Kumar (Trivandrum International School)','orders.php?order_ref=OB-CC3D3E',0,1,'2026-11-18 06:57:32'),(5,'order','New School Kit Order','New order OB-864333 placed by Rajesh Kumar for Aarav Kumar (Trivandrum International School)','orders.php?order_ref=OB-864333',0,1,'2026-11-18 07:19:04'),(6,'order','New Order Requisition: OB-D24C79','Rajesh Kumar placed order for Aarav Kumar (Class 5-A) at Trivandrum International School. Total: ₹ 798.00','orders.php?search=OB-D24C79',0,NULL,'2026-11-18 08:21:49'),(7,'enquiry','New Inquiry: Uniform Inquiry','Received from Adarsh Test (test@onbolt.com, 9876543210)','enquiries.php',0,NULL,'2026-11-18 10:20:21'),(8,'order','New Order Requisition: OB-2D2E87','Rajesh Kumar placed order for Aarav Kumar (Class 5-A) at Trivandrum International School. Total: ₹ 3,248.00','orders.php?search=OB-2D2E87',0,NULL,'2026-11-18 12:32:22'),(9,'order','New Order: OB-TEST99','Order for Aarav Kumar (Grade 5 - B) received. Total items: 0','orders.php?search=OB-TEST99',0,NULL,'2026-11-19 04:54:03'),(10,'order','New Order: OB-TEST88','Order for Aarav Kumar (Grade 5 - B) received. Total items: 3','orders.php?search=OB-TEST88',0,NULL,'2026-11-19 04:54:21'),(11,'order','New Order: OB-841039','Order for Abhay (3) received. Total items: 3','orders.php?search=OB-841039',0,NULL,'2026-11-19 07:00:23'),(12,'order','New Paid Order: OB-243968','Order for Aarav Kumar (5) received. Payment: Paid (Rs. 979) via UPI (Google Pay - Demo)','orders.php?search=OB-243968',0,NULL,'2026-11-19 10:01:55'),(13,'order','New Paid Order: OB-583293','Order for KEERTHI ADARSH M P (5) received. Payment: Paid (Rs. 979) via QR Code (Scan & Pay - Demo)','orders.php?search=OB-583293',0,NULL,'2026-11-19 10:05:46'),(14,'order','New Paid Order: OB-967860','Order for KEERTHI ADARSH M P (Kindergarten (Pre-KG) received. Payment: Paid (Rs. 979) via QR Code (Scan & Pay - Demo)','orders.php?search=OB-967860',0,NULL,'2026-11-19 10:21:51'),(15,'order','New Paid Order: OB-882153','Order for Advaith Menon (Grade 3 - A) received. Payment: Paid (Rs. 5149) via UPI (Google Pay - Demo)','orders.php?search=OB-882153',0,NULL,'2026-11-20 04:49:02'),(16,'order','New Paid Order: OB-593175','Order for KEERTHI ADARSH M P (Kindergarten (Pre-KG) received. Payment: Paid (Rs. 580) via UPI (Google Pay - Demo)','orders.php?search=OB-593175',0,NULL,'2026-11-20 06:16:00'),(17,'order','New Paid Order: OB-829261','Order for Rahul Sharma (Class 5 (Grade A)) received. Payment: Paid (Rs. 399) via UPI (Google Pay - Demo)','orders.php?search=OB-829261',0,NULL,'2026-11-20 06:29:11'),(18,'order','New Paid Order: OB-TIN-478643','Order for KEERTHI ADARSH M P (12 (A)) received. Payment: Paid (Rs. 979) via UPI (Google Pay - Demo)','orders.php?search=OB-TIN-478643',0,NULL,'2026-11-21 10:49:52'),(19,'order','New Paid Order: OB-TIN-530885','Order for Aarav Kumar (Class 5 (Grade 5)) received. Payment: Paid (Rs. 399) via UPI (Google Pay - Demo)','orders.php?search=OB-TIN-530885',0,NULL,'2026-11-21 10:50:20'),(20,'order','New Paid Order: OB-TIN-706477','Order for KEERTHI ADARSH M P (12 (A)) received. Payment: Paid (Rs. 979) via UPI (Google Pay - Demo)','orders.php?search=OB-TIN-706477',0,NULL,'2026-11-21 10:50:27'),(21,'order','New Order Initiated: TIS140127','Order for Aryan Sharma (Class 5-A) received. Payment Pending (Rs. 798) via Edviron Payment Gateway','orders.php?search=TIS140127',0,NULL,'2026-11-24 02:24:53'),(22,'order','New Order Initiated: TIE109124','Order for Rahul Kumar (Class 5 (Section A)) received. Payment Pending (Rs. 798) via Edviron Payment Gateway','orders.php?search=TIE109124',0,NULL,'2026-11-24 02:26:54'),(23,'order','New Order Initiated: GPS777889','Order for KEERTHI ADARSH M P (12 (A)) received. Payment Pending (Rs. 760) via Edviron Payment Gateway','orders.php?search=GPS777889',0,NULL,'2026-11-24 02:28:42'),(24,'order','New Order Initiated: GPS287296','Order for Aarav Sharma (LKG (Section A)) received. Payment Pending (Rs. 410) via UPI (PhonePe)','orders.php?search=GPS287296',0,NULL,'2026-11-24 02:36:55'),(25,'order','New Order Initiated: GPS805390','Order for KEERTHI ADARSH M P (12 (A)) received. Payment Pending (Rs. 760) via Net Banking (HDFC Bank)','orders.php?search=GPS805390',0,NULL,'2026-11-24 02:39:32'),(26,'order','New Order Initiated: GPS974502','Order for Flow Student (Grade 2) received. Payment Pending (Rs. 410) via UPI (PhonePe)','orders.php?search=GPS974502',0,NULL,'2026-11-24 05:33:05'),(27,'order','New Order Initiated: GPS661244','Order for KEERTHI ADARSH M P (12 (A)) received. Payment Pending (Rs. 760) via UPI (Google Pay)','orders.php?search=GPS661244',0,NULL,'2026-11-24 10:53:53'),(28,'order','New Order Initiated: GPS248485','Order for KEERTHI ADARSH M P (12 (A)) received. Payment Pending (Rs. 760) via Credit / Debit Card','orders.php?search=GPS248485',0,NULL,'2026-11-24 11:23:01'),(29,'order','New Order Initiated: GCB908976','Order for KEERTHI ADARSH M P (12 (A)) received. Payment Pending (Rs. 360) via UPI (Google Pay)','orders.php?search=GCB908976',0,NULL,'2026-11-24 11:32:21');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `product_name` varchar(180) NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `size` varchar(50) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,1,'White T-Shirt',NULL,'32',NULL,8,399.00,3192.00),(2,1,7,'Track Pants',NULL,'30',NULL,4,580.00,2320.00),(3,2,1,'White T-Shirt',NULL,'32',NULL,2,399.00,798.00),(4,2,7,'Track Pants',NULL,'30',NULL,1,580.00,580.00),(5,3,1,'White T-Shirt',NULL,'32',NULL,2,399.00,798.00),(6,3,7,'Track Pants',NULL,'30',NULL,1,580.00,580.00),(7,4,1,'White T-Shirt',NULL,'32',NULL,2,399.00,798.00),(8,4,7,'Track Pants',NULL,'30',NULL,1,580.00,580.00),(9,5,1,'White T-Shirt',NULL,'32',NULL,4,399.00,1596.00),(10,5,7,'Track Pants',NULL,'30',NULL,1,580.00,580.00),(13,8,1,'White T-Shirt',NULL,'32',NULL,2,399.00,798.00),(14,9,1,'White T-Shirt',NULL,'32',NULL,2,399.00,798.00),(15,9,11,'International School Complete Kit',NULL,'32',NULL,1,2450.00,2450.00),(16,11,1,'White T-Shirt',NULL,'22',NULL,2,399.00,798.00),(17,11,7,'Track Pants',NULL,'24',NULL,1,580.00,580.00),(20,13,1,'White T-Shirt',NULL,'28',NULL,1,399.00,399.00),(21,13,7,'Track Pants',NULL,'26',NULL,1,580.00,580.00),(22,14,1,'White T-Shirt',NULL,'26',NULL,1,399.00,399.00),(23,14,7,'Track Pants',NULL,'26',NULL,1,580.00,580.00),(24,15,1,'White T-Shirt',NULL,'24',NULL,1,399.00,399.00),(25,15,7,'Track Pants',NULL,'26',NULL,1,580.00,580.00),(26,16,1,'White T-Shirt',NULL,'30',NULL,1,399.00,399.00),(27,16,2,'Born White T-Shirts',NULL,'30',NULL,1,420.00,420.00),(28,16,7,'Track Pants',NULL,'30',NULL,1,580.00,580.00),(29,16,8,'Shorts',NULL,'28',NULL,1,350.00,350.00),(30,16,10,'Bottle Green Hoodies',NULL,'M',NULL,1,950.00,950.00),(31,16,11,'International School Complete Kit',NULL,'Standard Bundle',NULL,1,2450.00,2450.00),(32,17,7,'Track Pants',NULL,'26',NULL,1,580.00,580.00),(33,18,1,'White T-Shirt',NULL,'28',NULL,1,399.00,399.00),(34,19,1,'White T-Shirt',NULL,'38',NULL,1,399.00,399.00),(35,19,7,'Track Pants',NULL,'36',NULL,1,580.00,580.00),(36,20,1,'White T-Shirt',NULL,'28',NULL,1,399.00,399.00),(37,21,1,'White T-Shirt',NULL,'26',NULL,1,399.00,399.00),(38,21,7,'Track Pants',NULL,'24',NULL,1,580.00,580.00),(39,22,1,'School Uniform Polo',NULL,'28',NULL,2,399.00,798.00),(40,23,1,'White T-Shirt',NULL,'32',NULL,2,399.00,798.00),(41,24,12,'GPS Brookes Grey Polo Uniform',NULL,'26',NULL,1,410.00,410.00),(42,24,13,'GPS Brookes Grey Shorts',NULL,'24',NULL,1,350.00,350.00),(43,25,12,'GPS Brookes Grey Polo Uniform',NULL,'24',NULL,1,410.00,410.00),(44,26,12,'GPS Brookes Grey Polo Uniform',NULL,'30',NULL,1,410.00,410.00),(45,26,13,'GPS Brookes Grey Shorts',NULL,'24',NULL,1,350.00,350.00),(46,28,1,'GPS Brookes Grey Polo Uniform',NULL,'28',NULL,1,410.00,410.00),(47,29,38,'Yellow House Sports V-Neck T-Shirt',377,'28',NULL,2,399.00,798.00),(48,30,12,'GPS Brookes Grey Polo Uniform',NULL,'26',NULL,1,410.00,410.00),(49,30,13,'GPS Brookes Grey Shorts',NULL,'22',NULL,1,350.00,350.00),(50,31,12,'GPS Brookes Grey Polo Uniform',NULL,'24',NULL,1,410.00,410.00),(51,31,13,'GPS Brookes Grey Shorts',NULL,'24',NULL,1,350.00,350.00),(52,32,31,'GPS Sports Uniform Cap',NULL,'Regular',NULL,2,180.00,360.00);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_messages`
--

DROP TABLE IF EXISTS `order_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `parent_mobile` varchar(50) NOT NULL,
  `parent_name` varchar(150) NOT NULL,
  `message_text` text NOT NULL,
  `channel` varchar(30) DEFAULT 'WhatsApp',
  `sent_by` varchar(100) DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_messages`
--

LOCK TABLES `order_messages` WRITE;
/*!40000 ALTER TABLE `order_messages` DISABLE KEYS */;
INSERT INTO `order_messages` VALUES (1,1,'9847012345','Rajesh Kumar','Hello Rajesh Kumar, your uniform order OB-09B0E4 for Aarav Kumar has been confirmed by OnBolt. Total: Rs 5,512. Thank you!','WhatsApp','Admin','2026-11-19 06:34:17'),(2,1,'9847012345','Rajesh Kumar','Hello Rajesh Kumar, your uniform order OB-09B0E4 for Aarav Kumar has been confirmed by OnBolt. Total: Rs 5,512. Thank you!','WhatsApp','Admin','2026-11-19 06:34:54'),(3,1,'9847012345','Rajesh Kumar','Hello Rajesh Kumar, your uniform order OB-09B0E4 for Aarav Kumar has been confirmed by OnBolt. Total: Rs 5,512. Thank you!','WhatsApp','Admin','2026-11-19 06:35:33'),(4,11,'9876543210','Rajesh Kumar','Hello Rajesh Kumar, your uniform order OB-TEST88 for Aarav Kumar (Trivandrum International School) has been confirmed by OnBolt. Total: ₹ 1,378.00. Thank you for ordering!','WhatsApp','OnBolt Super Administrator','2026-11-19 06:39:55'),(5,13,'08778979416','Rajesh Kumar','Dear Rajesh Kumar, your uniform order #OB-243968 for Aarav Kumar (5) at Trivandrum International School has been CONFIRMED by OnBolt. Total Paid: ₹ 979.00. We will notify you once uniforms are ready for dispatch.','WhatsApp / Notification','OnBolt Super Administrator','2026-11-19 10:02:39'),(6,17,'08778979416','MANIKANDAN V','Dear MANIKANDAN V, your uniform order #OB-593175 for KEERTHI ADARSH M P (Kindergarten (Pre-KG) at Trivandrum International School has been CONFIRMED by OnBolt. Total Paid: ₹ 580.00. We will notify you once uniforms are ready for dispatch.','WhatsApp / Notification','OnBolt Super Administrator','2026-11-20 06:28:18'),(7,15,'08778979416','MANIKANDAN V','Dear MANIKANDAN V, your uniform order #OB-967860 for KEERTHI ADARSH M P (Kindergarten (Pre-KG) at GPS Brookes - Trivandrum (IB) has been CONFIRMED by OnBolt. Total Paid: ₹ 979.00. We will notify you once uniforms are ready for dispatch.','WhatsApp / Notification','OnBolt Super Administrator','2026-11-20 06:51:09');
/*!40000 ALTER TABLE `order_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `comment` text DEFAULT NULL,
  `changed_by` varchar(100) NOT NULL DEFAULT 'System',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_osh_order` (`order_id`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_history`
--

LOCK TABLES `order_status_history` WRITE;
/*!40000 ALTER TABLE `order_status_history` DISABLE KEYS */;
INSERT INTO `order_status_history` VALUES (1,1,NULL,'Pending','Order created by parent','Parent','2026-11-18 06:28:48'),(2,2,NULL,'Pending','Order created by parent','Parent','2026-11-18 06:29:09'),(3,3,NULL,'Pending','Order created by parent','Parent','2026-11-18 06:40:32'),(4,4,NULL,'Pending','Order created by parent','Parent','2026-11-18 06:57:32'),(5,5,NULL,'Pending','Order created by parent','Parent','2026-11-18 07:19:04'),(6,8,NULL,'Pending','Order requisition logged successfully','Parent','2026-11-18 08:21:49'),(7,8,NULL,'Production','Fabric cutting and crest embroidery in progress','Admin','2026-11-18 08:23:26'),(8,9,NULL,'Pending','Order requisition logged successfully','Parent','2026-11-18 12:32:22'),(9,10,NULL,'Pending','WhatsApp Order Requisition Logged','System','2026-11-19 04:54:03'),(10,11,NULL,'Pending','WhatsApp Order Requisition Logged','System','2026-11-19 04:54:21'),(12,13,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-25740931). Awaiting Admin Confirmation.','System','2026-11-19 10:01:55'),(13,13,'Pending','Confirmed','Order confirmed by Administrator','OnBolt Super Administrator','2026-11-19 10:02:39'),(14,14,NULL,'Pending','Order created with Paid status via QR Code (Scan & Pay - Demo) (Ref: TXN-DEMO-26795734). Awaiting Admin Confirmation.','System','2026-11-19 10:05:46'),(15,15,NULL,'Pending','Order created with Paid status via QR Code (Scan & Pay - Demo) (Ref: TXN-DEMO-83239547). Awaiting Admin Confirmation.','System','2026-11-19 10:21:51'),(16,16,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-72578876). Awaiting Admin Confirmation.','System','2026-11-20 04:49:02'),(17,17,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-25205699). Awaiting Admin Confirmation.','System','2026-11-20 06:16:00'),(18,17,'Previous','On the way','Status set to On the way. ','OnBolt Super Administrator','2026-11-20 06:27:18'),(19,17,'On the way','Confirmed','Order confirmed by Administrator','OnBolt Super Administrator','2026-11-20 06:28:18'),(20,17,'Previous','On the way','Status set to On the way. ','OnBolt Super Administrator','2026-11-20 06:28:48'),(21,18,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-58015888). Awaiting Admin Confirmation.','System','2026-11-20 06:29:11'),(22,15,'Pending','Confirmed','Order confirmed by Administrator','OnBolt Super Administrator','2026-11-20 06:51:09'),(23,18,'Confirmed','Packed','Status updated from Confirmed to Packed','OnBolt Super Administrator','2026-11-21 06:29:18'),(24,16,'Confirmed','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 06:29:36'),(25,17,'On the way','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 06:29:36'),(26,18,'Packed','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 06:29:36'),(27,15,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:00:35'),(28,16,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:00:35'),(29,17,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:00:35'),(30,18,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:00:35'),(31,5,'Pending','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:01:39'),(32,9,'Pending','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:01:39'),(33,14,'Pending','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:01:39'),(34,4,'Pending','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:02:01'),(35,5,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:02:01'),(36,9,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:02:01'),(37,14,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:02:01'),(38,1,'Pending','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(39,2,'Pending','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(40,3,'Pending','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(41,4,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(42,5,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(43,8,'Production','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(44,9,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(45,10,'Pending','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(46,11,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(47,13,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(48,14,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(49,15,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(50,16,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(51,17,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(52,18,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 07:06:17'),(53,18,'Confirmed','On the way','Status updated from Confirmed to On the way','OnBolt Super Administrator','2026-11-21 08:38:31'),(54,19,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-60117861). Awaiting Admin Confirmation.','System','2026-11-21 10:49:52'),(55,20,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-23008372). Awaiting Admin Confirmation.','System','2026-11-21 10:50:20'),(56,21,NULL,'Pending','Order created with Paid status via UPI (Google Pay - Demo) (Ref: TXN-DEMO-54320077). Awaiting Admin Confirmation.','System','2026-11-21 10:50:27'),(57,21,'Confirmed','Confirmed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 10:53:14'),(58,21,'Confirmed','Packed','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 11:05:00'),(59,18,'On the way','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 11:05:25'),(60,20,'Confirmed','Production','Updated via Bulk Status Update tool','OnBolt Super Administrator','2026-11-21 11:05:25'),(61,21,'Packed','Delivered','Status updated from Packed to Delivered','OnBolt Super Administrator','2026-11-21 11:16:56'),(62,19,'Confirmed','Dispatched by Hand / Delivered','Dispatched by Hand at school counter to MANIKANDAN V. Staff: RS. Code: COD-4193','Software Admin Operations','2026-11-23 06:09:33'),(63,20,'Production','Cancellation Requested','Cancellation & refund requested. Reason: UAT Test Cancel. Pending Admin Confirmation.','Software User / Staff','2026-11-23 06:10:55'),(64,20,'Cancellation Requested','Cancelled','Refund confirmed and processed (Rs. 399). Net sales reconciled.','Software Admin Operations','2026-11-23 06:13:05'),(65,21,'Delivered','Dispatched by hand','Status updated from Delivered to Dispatched by hand','OnBolt Super Administrator','2026-11-23 10:23:23'),(66,21,'Dispatched by hand','Refunded','Status updated from Dispatched by hand to Refunded','OnBolt Super Administrator','2026-11-23 10:23:27'),(67,22,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-PENDING-EFEC31A1). Awaiting parent payment completion.','System','2026-11-24 02:24:53'),(68,22,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: TXN-203601EC). Order status set to Confirmed.','Payment Gateway','2026-11-24 02:25:11'),(69,22,'Confirmed','Confirmed','Payment verified via Edviron Webhook. Status: SUCCESS. Bank Ref: 998877665544','Webhook','2026-11-24 02:25:42'),(70,23,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-PENDING-3F268C9E). Awaiting parent payment completion.','System','2026-11-24 02:26:54'),(71,23,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: TXN-4AA7D379). Order status set to Confirmed.','Payment Gateway','2026-11-24 02:26:55'),(72,24,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-PENDING-1893F3D1). Awaiting parent payment completion.','System','2026-11-24 02:28:42'),(73,24,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: TXN-CF69EDBA). Order status set to Confirmed.','Payment Gateway','2026-11-24 02:28:43'),(74,25,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-PENDING-BB4D17B7). Awaiting parent payment completion.','System','2026-11-24 02:36:55'),(75,25,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: TXN-BBE6EBE3). Order status set to Confirmed.','Payment Gateway','2026-11-24 02:36:56'),(76,26,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-PENDING-D19D4890). Awaiting parent payment completion.','System','2026-11-24 02:39:32'),(77,26,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: TXN-AEEB7216). Order status set to Confirmed.','Payment Gateway','2026-11-24 02:39:32'),(78,28,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-6DAB35A3). Awaiting parent payment completion.','System','2026-11-24 05:33:05'),(79,28,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: TXN-26364719). Order status set to Confirmed.','Payment Gateway','2026-11-24 05:33:05'),(80,30,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-TEST-774CE3D1). Awaiting parent payment completion.','System','2026-11-24 10:53:53'),(81,30,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: EDV-TEST-774CE3D1). Order status set to Confirmed.','Payment Gateway','2026-11-24 10:53:54'),(82,31,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-TEST-E9445D0C). Awaiting parent payment completion.','System','2026-11-24 11:23:01'),(83,31,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: EDV-TEST-E9445D0C). Order status set to Confirmed.','Payment Gateway','2026-11-24 11:23:02'),(84,32,NULL,'Pending','Order initiated. Gateway Collect Request created (Ref: EDV-TEST-69BCF701). Awaiting parent payment completion.','System','2026-11-24 11:32:21'),(85,32,'Pending','Confirmed','Payment verified successfully via Edviron Gateway (Ref: EDV-TEST-69BCF701). Order status set to Confirmed.','Payment Gateway','2026-11-24 11:32:21');
/*!40000 ALTER TABLE `order_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_reference` varchar(60) NOT NULL,
  `school_id` int(10) unsigned NOT NULL,
  `sub_school_id` int(10) unsigned DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `parent_name` varchar(150) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `grade_class` varchar(50) NOT NULL,
  `mobile` varchar(50) NOT NULL,
  `alt_mobile` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `delivery_address` text NOT NULL,
  `pincode` varchar(20) NOT NULL,
  `state` varchar(100) NOT NULL DEFAULT 'Kerala',
  `total_items` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refund_status` enum('None','Pending','Approved','Rejected') NOT NULL DEFAULT 'None',
  `refund_requested_by` varchar(100) DEFAULT NULL,
  `refund_approved_by` varchar(100) DEFAULT NULL,
  `refund_notes` text DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `payment_status` varchar(60) NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(50) NOT NULL DEFAULT 'WhatsApp / Offline',
  `order_status` varchar(60) NOT NULL DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `courier_name` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `fulfillment_type` enum('Courier','Handover','Counter') NOT NULL DEFAULT 'Courier',
  `handover_staff` varchar(100) DEFAULT NULL,
  `handover_recipient` varchar(150) DEFAULT NULL,
  `handover_code` varchar(50) DEFAULT NULL,
  `handover_at` datetime DEFAULT NULL,
  `exchange_requested` tinyint(1) DEFAULT 0,
  `exchange_details` text DEFAULT NULL,
  `pdf_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_ref` (`order_reference`),
  KEY `idx_orders_school` (`school_id`),
  KEY `idx_orders_parent` (`parent_id`),
  KEY `idx_orders_status` (`order_status`),
  CONSTRAINT `fk_orders_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'TIE091014',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Kerala',12,5512.00,0.00,0.00,5512.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp / Offline Order','Confirmed','Order requisition placed through parent portal',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 06:28:48','2026-11-23 18:10:20'),(2,'TIE257912',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Kerala',3,1378.00,0.00,0.00,1378.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp / Offline Order','Confirmed','Order requisition placed through parent portal',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 06:29:09','2026-11-23 18:10:20'),(3,'TIE049021',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Kerala',3,1378.00,0.00,0.00,1378.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp / Offline Order','Confirmed','Order requisition placed through parent portal',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 06:40:32','2026-11-23 18:10:20'),(4,'TIE331234',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Kerala',3,1378.00,0.00,0.00,1378.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp / Offline Order','Confirmed','Order requisition placed through parent portal',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 06:57:32','2026-11-23 18:10:20'),(5,'TIE864333',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Kerala',5,2176.00,0.00,0.00,2176.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp / Offline Order','Confirmed','Order requisition placed through parent portal',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 07:19:04','2026-11-23 18:10:20'),(8,'TIE424790',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','9847012345','','rajesh.kumar@example.com','Villa 14, Kazhakkoottam','695582','Kerala',2,798.00,0.00,0.00,798.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp / Offline Order','Confirmed','Fabric cutting and crest embroidery in progress',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 08:21:49','2026-11-23 18:10:20'),(9,'TIE522870',1,NULL,1,'Rajesh Kumar','Aarav Kumar',NULL,'Class 5-A','+91 9847123456','','rajesh.kumar@example.com','Villa 14, Palm Grove, Kazhakkoottam','695582','Kerala',3,3248.00,0.00,0.00,3248.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','Campus Counter & WhatsApp','Confirmed','Placed through unified school ordering portal',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-18 12:32:22','2026-11-23 18:10:20'),(10,'TIE699100',1,NULL,8,'Rajesh Kumar','Aarav Kumar',NULL,'Grade 5 - B','9876543210','','rajesh.kumar@example.com','3rd Floor, Rema Plaza, S.S Kovil Road, Thampanoor','695001','Trivandrum, Kerala',0,0.00,0.00,0.00,0.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp Direct','Confirmed','State/District: Trivandrum, Kerala. Placed via 4-step uniform ordering wizard.',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-19 04:54:03','2026-11-23 18:10:20'),(11,'TIE788200',1,NULL,NULL,'Rajesh Kumar','Aarav Kumar',NULL,'Grade 5 - B','9876543210','','rajesh.kumar@example.com','3rd Floor, Rema Plaza, S.S Kovil Road, Thampanoor','695001','Trivandrum, Kerala',3,1378.00,0.00,0.00,1378.00,0.00,'None',NULL,NULL,NULL,NULL,'Pending','WhatsApp Direct','Confirmed','State/District: Trivandrum, Kerala. Placed via 4-step uniform ordering wizard.',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-19 04:54:21','2026-11-23 18:10:20'),(13,'TIE243968',1,NULL,8,'Rajesh Kumar','Aarav Kumar',NULL,'5','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA. 695043','695043','Trivandrum, Kerala',2,979.00,0.00,0.00,979.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay - Demo)','Confirmed','State/District: Trivandrum, Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-25740931).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-19 10:01:55','2026-11-23 18:10:20'),(14,'TIE583293',1,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P',NULL,'5','08778979416','8778979416','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,979.00,0.00,0.00,979.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','QR Code (Scan & Pay - Demo)','Confirmed','State/District: Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-26795734).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-19 10:05:46','2026-11-23 18:10:20'),(15,'GIB967860',16,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P',NULL,'Kindergarten (Pre-KG','08778979416','8778979416','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,979.00,0.00,0.00,979.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','QR Code (Scan & Pay - Demo)','Confirmed','State/District: Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-83239547).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-19 10:21:51','2026-11-23 18:10:20'),(16,'TIE882153',1,NULL,8,'Dr. Suresh Menon','Advaith Menon',NULL,'Grade 3 - A','9847012345','','suresh.menon@example.com','Flat 4B, Kowdiar Manor, Kowdiar','695003','Trivandrum, Kerala',6,5149.00,0.00,0.00,5149.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay - Demo)','Confirmed','State/District: Trivandrum, Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-72578876).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-20 04:49:02','2026-11-23 18:10:20'),(17,'TIE593175',1,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P',NULL,'Kindergarten (Pre-KG','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',1,580.00,0.00,0.00,580.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay - Demo)','Confirmed','State/District: Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-25205699).','','','Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-20 06:16:00','2026-11-23 18:10:20'),(18,'TIE829261',1,NULL,8,'Rajesh Sharma','Rahul Sharma','Boy','Class 5 (Grade A)','9847012345','','rajesh@example.com','Kowdiar, Trivandrum','695003','Trivandrum, Kerala',1,399.00,0.00,0.00,399.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay - Demo)','Production','State/District: Trivandrum, Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-58015888).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-20 06:29:11','2026-11-23 18:10:20'),(19,'TIE478643',1,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,979.00,0.00,0.00,979.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay - Demo)','Dispatched by Hand / Delivered','State/District: Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-60117861).','Counter Collection / Handover','COD-4193','Handover','RS','MANIKANDAN V','COD-4193','2026-11-23 11:39:33',0,NULL,NULL,'2026-11-21 10:49:52','2026-11-23 18:10:20'),(20,'TIE530885',1,NULL,8,'Rajesh Kumar','Aarav Kumar','Boy','Class 5 (Grade 5)','9876543210','','rajesh.kumar@example.com','123, Kowdiar Main Road, Kowdiar','695003','Trivandrum, Kerala',1,399.00,0.00,0.00,399.00,399.00,'Approved','Software User / Staff','Software Admin Operations','UAT Test Cancel','2026-11-23 11:43:05','Refunded','UPI (Google Pay - Demo)','Cancelled','State/District: Trivandrum, Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-23008372).\n[Refund Approved by Software Admin Operations at 2026-11-23 11:43:05]: Approved and processed by Admin',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-21 10:50:20','2026-11-23 18:10:20'),(21,'TIE706477',1,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,979.00,0.00,0.00,979.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay - Demo)','Refunded','State/District: Kerala. Placed via uniform ordering portal with Demo Payment (Txn: TXN-DEMO-54320077).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-21 10:50:27','2026-11-23 18:10:20'),(22,'TIS140127',1,NULL,NULL,'Mr. Test Parent','Aryan Sharma','Boy','Class 5-A','9876543210','9876543211','parent.test@example.com','Villa 42, Campus Enclave, Trivandrum','695582','Trivandrum, Kerala',2,798.00,0.00,0.00,798.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','Edviron Payment Gateway','Confirmed','State/District: Trivandrum, Kerala. Initiated via online uniform ordering portal. Gateway: Edviron Payment Gateway.',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 02:24:52','2026-11-24 02:25:42'),(23,'TIE109124',28,NULL,8,'Suresh Kumar','Rahul Kumar','Boy','Class 5 (Section A)','9876543210','9876543211','testparent@example.com','Flat 4B, Emerald Heights, MG Road','695001','Trivandrum, Kerala',2,798.00,0.00,0.00,798.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','Edviron Payment Gateway','Confirmed','State/District: Trivandrum, Kerala. Initiated via online uniform ordering portal. Gateway: Edviron Payment Gateway.',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 02:26:54','2026-11-24 02:26:55'),(24,'GPS777889',19,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','08778979416','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,760.00,0.00,0.00,760.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','Edviron Payment Gateway','Confirmed','State/District: Kerala. Initiated via online uniform ordering portal. Gateway: Edviron Payment Gateway.',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 02:28:42','2026-11-24 02:28:43'),(25,'GPS287296',19,NULL,8,'Rajesh Sharma','Aarav Sharma','Boy','LKG (Section A)','9876543210','','rajesh.sharma@example.com','Flat 4B, Emerald Heights, MG Road','695001','Trivandrum, Kerala',1,410.00,0.00,0.00,410.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (PhonePe)','Confirmed','State/District: Trivandrum, Kerala. Initiated via online uniform ordering portal. Gateway: UPI (PhonePe).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 02:36:55','2026-11-24 02:36:56'),(26,'GPS805390',19,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,760.00,0.00,0.00,760.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','Net Banking (HDFC Bank)','Confirmed','State/District: Kerala. Initiated via online uniform ordering portal. Gateway: Net Banking (HDFC Bank).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 02:39:32','2026-11-24 02:39:32'),(28,'GPS974502',19,NULL,NULL,'Gateway Flow Test','Flow Student','Boy','Grade 2','9876543210','','gateway@example.com','','','Kerala',1,410.00,0.00,0.00,410.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (PhonePe)','Confirmed','State/District: Kerala. Initiated via online uniform ordering portal. Gateway: UPI (PhonePe).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 05:33:05','2026-11-24 05:33:05'),(29,'GCB556977',20,17,8,'Keerthi Parent','Adarsh Testing Student','Boy','Class 3-A','9876543210','','','Direct Counter Handover - OnBolt Desk','695001','Kerala',2,798.00,0.00,0.00,798.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','Cash (Counter)','Completed','Walk-in Order created by Admin Admin. Cash Tendered: ₹1,000.00, Change: ₹202.00. Test walk-in order created via desk',NULL,NULL,'Counter','Admin','Keerthi Parent (Adarsh Testing Student)',NULL,'2026-11-24 10:04:03',0,NULL,NULL,'2026-11-24 09:04:03','2026-11-24 09:04:03'),(30,'GPS661244',19,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,760.00,0.00,0.00,760.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay)','Confirmed','State/District: Kerala. Initiated via online uniform ordering portal. Gateway: UPI (Google Pay).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 10:53:53','2026-11-24 10:53:54'),(31,'GPS248485',19,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,760.00,0.00,0.00,760.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','Credit / Debit Card','Confirmed','State/District: Kerala. Initiated via online uniform ordering portal. Gateway: Credit / Debit Card.',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 11:23:01','2026-11-24 11:23:02'),(32,'GCB908976',17,NULL,8,'MANIKANDAN V','KEERTHI ADARSH M P','Boy','12 (A)','08778979416','','keerthiadarshmp@gmail.com','THIRUVANANTHAPURAM, KERALA','695043','Kerala',2,360.00,0.00,0.00,360.00,0.00,'None',NULL,NULL,NULL,NULL,'Paid','UPI (Google Pay)','Confirmed','State/District: Kerala. Initiated via online uniform ordering portal. Gateway: UPI (Google Pay).',NULL,NULL,'Courier',NULL,NULL,NULL,NULL,0,NULL,NULL,'2026-11-24 11:32:20','2026-11-24 11:32:21');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!50001 DROP VIEW IF EXISTS `pages`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `pages` AS SELECT
 1 AS `id`,
  1 AS `slug`,
  1 AS `title`,
  1 AS `meta_title`,
  1 AS `meta_description`,
  1 AS `banner_title`,
  1 AS `banner_subtitle`,
  1 AS `content`,
  1 AS `status`,
  1 AS `created_at`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `parent_students`
--

DROP TABLE IF EXISTS `parent_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parent_students` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `relationship` varchar(50) NOT NULL DEFAULT 'Parent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_student` (`parent_id`,`student_id`),
  KEY `fk_ps_student` (`student_id`),
  CONSTRAINT `fk_ps_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parent_students`
--

LOCK TABLES `parent_students` WRITE;
/*!40000 ALTER TABLE `parent_students` DISABLE KEYS */;
INSERT INTO `parent_students` VALUES (1,1,1,'Father','2026-11-18 06:20:35'),(2,2,2,'Mother','2026-11-18 06:20:35'),(3,3,3,'Mother','2026-11-18 06:20:35'),(7,8,7,'Father','2026-11-19 04:47:12');
/*!40000 ALTER TABLE `parent_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parents`
--

DROP TABLE IF EXISTS `parents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `login_id` varchar(80) NOT NULL,
  `parent_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `alt_phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `state` varchar(100) DEFAULT 'Kerala',
  `password_hash` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_school_login` (`school_id`,`login_id`),
  KEY `idx_parents_phone` (`phone`),
  CONSTRAINT `fk_parents_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parents`
--

LOCK TABLES `parents` WRITE;
/*!40000 ALTER TABLE `parents` DISABLE KEYS */;
INSERT INTO `parents` VALUES (1,1,'TIS202601','Rajesh Kumar','rajesh.kumar@example.com','9847012345','9847054321','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Kerala',NULL,1,'2026-11-19 10:53:14','2026-11-18 06:20:35','2026-11-19 05:23:14'),(2,1,'TIS202602','Priya Nair','priya.nair@example.com','9895012345','9895054321','Skyline Apartments Flat 4B, Kowdiar','695003','Kerala',NULL,1,NULL,'2026-11-18 06:20:35','2026-11-18 06:20:35'),(3,1,'PARENT101','Sunitha Menon','sunitha.menon@example.com','9447012345','','TC 12/456, Sasthamangalam','695010','Kerala',NULL,1,NULL,'2026-11-18 06:20:35','2026-11-18 06:20:35'),(8,1,'Trins01','Rajesh Kumar','rajesh.kumar@example.com','9876543210',NULL,NULL,NULL,'Kerala',NULL,1,'2026-11-19 12:25:30','2026-11-19 04:47:12','2026-11-19 06:55:30');
/*!40000 ALTER TABLE `parents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `transaction_ref` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Offline / COD',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `status` enum('Pending','Completed','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `gateway_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pay_order` (`order_id`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,13,'TXN-DEMO-25740931','UPI (Google Pay - Demo)',979.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-19 10:01:55'),(2,14,'TXN-DEMO-26795734','QR Code (Scan & Pay - Demo)',979.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-19 10:05:46'),(3,15,'TXN-DEMO-83239547','QR Code (Scan & Pay - Demo)',979.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-19 10:21:51'),(4,16,'TXN-DEMO-72578876','UPI (Google Pay - Demo)',5149.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-20 04:49:02'),(5,17,'TXN-DEMO-25205699','UPI (Google Pay - Demo)',580.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-20 06:16:00'),(6,18,'TXN-DEMO-58015888','UPI (Google Pay - Demo)',399.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-20 06:29:11'),(7,19,'TXN-DEMO-60117861','UPI (Google Pay - Demo)',979.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-21 10:49:52'),(8,20,'TXN-DEMO-23008372','UPI (Google Pay - Demo)',399.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-21 10:50:20'),(9,21,'TXN-DEMO-54320077','UPI (Google Pay - Demo)',979.00,'INR','Completed','Demo Payment Authorized (Test Mode)','2026-11-21 10:50:27'),(10,20,'REF-F891C89652','Refund / Reversal',399.00,'INR','Refunded','Refund released by Admin: Software Admin Operations. Reason: Approved and processed by Admin','2026-11-23 06:13:05'),(11,22,'998877665544','Edviron - Upi',798.00,'INR','Completed','{\"collect_id\":\"67f616ce02821266c233317e\",\"amount\":798,\"status\":\"SUCCESS\",\"custom_order_id\":\"TIS140127\",\"bank_reference\":\"998877665544\",\"payment_method\":\"upi\"}','2026-11-24 02:25:42'),(12,23,'TXN-4AA7D379','Edviron - Edviron Gateway',798.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"\"}','2026-11-24 02:26:55'),(13,24,'TXN-CF69EDBA','Edviron - Edviron Gateway',760.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"\"}','2026-11-24 02:28:43'),(14,25,'TXN-BBE6EBE3','Edviron - Edviron Gateway',410.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"\"}','2026-11-24 02:36:56'),(15,26,'TXN-AEEB7216','Edviron - Edviron Gateway',760.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"\"}','2026-11-24 02:39:32'),(16,28,'TXN-26364719','Edviron - Edviron Gateway',410.00,'INR','Completed','{\"status\":\"SUCCESS\",\"custom_order_id\":\"GPS974502\"}','2026-11-24 05:33:05'),(17,30,'EDV-TEST-774CE3D1','Edviron - Edviron Gateway',760.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"EDV-TEST-774CE3D1\"}','2026-11-24 10:53:54'),(18,31,'EDV-TEST-E9445D0C','Edviron - Edviron Gateway',760.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"EDV-TEST-E9445D0C\"}','2026-11-24 11:23:02'),(19,32,'EDV-TEST-69BCF701','Edviron - Edviron Gateway',360.00,'INR','Completed','{\"status\":\"SUCCESS\",\"collect_id\":\"EDV-TEST-69BCF701\"}','2026-11-24 11:32:21');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_perm_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'Manage Content','content_manage','cms','Edit pages, banners and homepage sections'),(2,'Manage Schools','schools_manage','schools','Create, update, delete school profiles'),(3,'Manage Parents','parents_manage','users','Create and update parent login IDs'),(4,'Manage Students','students_manage','users','Manage enrolled students'),(5,'Manage Products','products_manage','catalog','Create and edit products and variants'),(6,'Manage Inventory','inventory_manage','catalog','Track and adjust stock levels'),(7,'Manage Orders','orders_manage','orders','View orders, update order status and notes'),(8,'Manage Payments','payments_manage','orders','Review payments and transactions'),(9,'View Reports','reports_view','reports','Access analytics and export data'),(10,'Manage Settings','settings_manage','settings','Configure site branding and operational settings'),(11,'View Dashboard','dashboard_view','dashboard','Access operational dashboard and metrics'),(12,'Refund Orders','orders_refund','orders','Execute and approve order refunds'),(13,'Partner Percentage','partner_percentage_view','reports','View vendor commission and partner splits table'),(14,'Manage Users','users_manage','users','Create, edit, and delete admin and school users'),(15,'Manage Privileges','privileges_manage','settings','Configure role permissions and access rights');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portfolio`
--

DROP TABLE IF EXISTS `portfolio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `portfolio` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `category` varchar(80) NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `client_name` varchar(120) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_cat` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portfolio`
--

LOCK TABLES `portfolio` WRITE;
/*!40000 ALTER TABLE `portfolio` DISABLE KEYS */;
INSERT INTO `portfolio` VALUES (1,'School Uniform Manufacturing Excellence','Academic Uniforms',NULL,'International School, Trivandrum','assets/images/home/Untitled-design-52.jpg','Complete customized uniform apparel design with institutional heraldry embroidery and contrast collar trims.',1,1,1,'2026-11-18 06:20:35'),(2,'Sports House & Athletic Apparel','Sports Wear',NULL,'Partner School Athletic Division','assets/images/home/Untitled-design-53.jpg','Engineered poly-blend moisture-wicking athletic jerseys and track shorts.',1,2,1,'2026-11-18 06:20:35'),(3,'Complete Institutional School Kits','Complete Kits',NULL,'Educational Trust','assets/images/home/Untitled-design-54.jpg','Vibrant, soft-cotton pre-primary uniforms and kid-friendly bags.',1,3,1,'2026-11-18 06:20:35'),(4,'Executive Corporate Gifting & Hampers','Corporate Gifting',NULL,'Enterprise Partners','assets/images/home/Untitled-design-45.jpg','Colour-coded inter-house track suits and active accessories.',1,4,1,'2026-11-18 06:20:35'),(5,'Custom Embroidery & Crest Branding','Branding',NULL,'Academic Institutions','assets/images/home/16435.jpg','Bespoke dungarees, checked shirts, and anti-static skirts.',0,5,1,'2026-11-18 06:20:35'),(6,'Hospitality & Workwear Textiles','Workwear',NULL,'Hospitality Partners','assets/images/home/2147848715.jpg','Premium vacuum flasks, customized diaries, luxury pen sets and branded tech sleeves.',1,6,1,'2026-11-18 06:20:35');
/*!40000 ALTER TABLE `portfolio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,'School Uniform Manufacturing','school-uniform-manufacturing','Precision tailored academic uniforms, daily polos, trousers, skirts, and blazers made with breathable combed cotton fabric.','assets/images/home/Untitled-design-44.jpg','ph-t-shirt',1,1,'2026-11-18 06:20:35','2026-11-19 05:19:21'),(2,'Complete School Kit Supply','complete-school-kit-supply','All-in-one student kits including academic wear, sports house tees, track suits, backpacks, socks, and accessories.','assets/images/home/593.jpg','ph-package',2,1,'2026-11-18 06:20:35','2026-11-19 05:19:21'),(3,'Custom Logo & Branding','custom-logo-branding','Computerized Tajima multi-head embroidery, high-density plastisol printing, woven crest badges, and customized branding.','assets/images/home/426154560_8377ed1a-24eb-4801-964b-e8ac0a38b7be-scaled.jpg','ph-seal-check',3,1,'2026-11-18 06:20:35','2026-11-19 05:19:21'),(4,'Personalized Products','personalized-products','Customized apparel, personalized student name labels, customized school bags, water bottles, and stationery.','assets/images/home/26201.jpg','ph-gift',4,1,'2026-11-18 06:20:35','2026-11-19 05:19:21'),(5,'Corporate Gifts','corporate-gifts','Curated executive gift boxes, corporate polo shirts, tech gadgets, trophies, drinkware, and premium event giveaways.','assets/images/home/1899793.jpg',NULL,5,1,'2026-11-19 05:19:21','2026-11-19 05:19:21'),(6,'Hospitality Merchandise','hospitality-merchandise','High-durability chef coats, service aprons, hotel staff uniforms, linens, and branded hospitality workwear.','assets/images/home/900357_62924-OALPFP-207.jpg.jpeg',NULL,6,1,'2026-11-19 05:19:21','2026-11-19 05:19:21');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pi_product` (`product_id`),
  CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `size` varchar(50) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `price_override` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 100,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pv_product` (`product_id`),
  CONSTRAINT `fk_pv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (18,1,'18',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(19,1,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(20,1,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(21,1,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(22,1,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(23,1,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(24,1,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(25,1,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(26,1,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(27,1,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(28,1,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(29,1,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(30,1,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(31,1,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(32,1,'46',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(33,2,'18',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(34,2,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(35,2,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(36,2,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(37,2,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(38,2,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(39,2,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(40,2,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(41,2,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(42,2,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(43,2,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(44,2,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(45,2,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(46,2,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(47,2,'46',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(48,3,'18',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(49,3,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(50,3,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(51,3,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(52,3,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(53,3,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(54,3,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(55,3,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(56,3,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(57,3,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(58,3,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(59,3,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(60,3,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(61,3,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(62,3,'46',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(63,4,'18',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(64,4,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(65,4,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(66,4,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(67,4,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(68,4,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(69,4,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(70,4,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(71,4,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(72,4,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(73,4,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(74,4,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(75,4,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(76,4,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(77,4,'46',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(78,5,'18',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(79,5,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(80,5,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(81,5,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(82,5,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(83,5,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(84,5,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(85,5,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(86,5,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(87,5,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(88,5,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(89,5,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(90,5,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(91,5,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(92,5,'46',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(93,6,'18',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(94,6,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(95,6,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(96,6,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(97,6,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(98,6,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(99,6,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(100,6,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(101,6,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(102,6,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(103,6,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(104,6,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(105,6,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(106,6,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(107,6,'46',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(108,7,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(109,7,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(110,7,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(111,7,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(112,7,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(113,7,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(114,7,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(115,7,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(116,7,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(117,7,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(118,7,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(119,7,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(120,7,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(121,8,'20',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(122,8,'22',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(123,8,'24',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(124,8,'26',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(125,8,'28',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(126,8,'30',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(127,8,'32',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(128,8,'34',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(129,8,'36',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(130,8,'38',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(131,8,'40',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(132,8,'42',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(133,8,'44',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(134,9,'2',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(135,9,'3',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(136,9,'4',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(137,9,'5',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(138,9,'6',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(139,9,'7',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(140,10,'S',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(141,10,'M',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(142,10,'L',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(143,10,'XL',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(144,10,'XXL',NULL,NULL,NULL,100,1,'2026-11-19 04:47:12','2026-11-19 04:47:12'),(145,12,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(146,12,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(147,12,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(148,12,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(149,12,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(150,12,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(151,12,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(152,12,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(153,12,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(154,12,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(155,12,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(156,13,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(157,13,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(158,13,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(159,13,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(160,13,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(161,13,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(162,13,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(163,13,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(164,13,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(165,14,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(166,14,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(167,14,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(168,14,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(169,14,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(170,14,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(171,14,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(172,14,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(173,14,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(174,15,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(175,15,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(176,15,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(177,15,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(178,15,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(179,15,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(180,15,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(181,15,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(182,15,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(183,15,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(184,15,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(185,16,'Regular',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(186,16,'Adjustable',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(187,17,'Small (24-28)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(188,17,'Medium (30-34)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(189,17,'Large (36-40)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(190,18,'2',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(191,18,'3',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(192,18,'4',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(193,18,'5',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(194,18,'6',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(195,18,'7',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(196,19,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(197,19,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(198,19,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(199,19,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(200,19,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(201,19,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(202,19,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(203,19,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(204,19,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(205,20,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(206,20,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(207,20,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(208,20,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(209,20,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(210,20,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(211,20,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(212,20,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(213,20,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(214,20,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(215,20,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(216,21,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(217,21,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(218,21,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(219,21,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(220,21,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(221,21,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(222,21,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(223,21,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(224,21,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(225,21,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(226,21,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(227,22,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(228,22,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(229,22,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(230,22,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(231,22,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(232,22,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(233,22,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(234,22,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(235,22,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(236,22,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(237,22,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(238,23,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(239,23,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(240,23,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(241,23,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(242,23,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(243,23,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(244,23,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(245,23,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(246,23,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(247,23,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(248,23,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(249,24,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(250,24,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(251,24,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(252,24,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(253,24,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(254,24,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(255,24,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(256,24,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(257,24,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(258,24,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(259,24,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(260,25,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(261,25,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(262,25,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(263,25,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(264,25,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(265,25,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(266,25,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(267,25,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(268,25,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(269,26,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(270,26,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(271,26,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(272,26,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(273,26,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(274,26,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(275,26,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(276,26,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(277,26,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(278,26,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(279,26,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(280,27,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(281,27,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(282,27,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(283,27,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(284,27,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(285,27,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(286,27,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(287,27,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(288,27,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(289,28,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(290,28,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(291,28,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(292,28,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(293,28,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(294,28,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(295,28,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(296,28,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(297,28,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(298,28,'42',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(299,29,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(300,29,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(301,29,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(302,29,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(303,29,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(304,29,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(305,29,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(306,29,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(307,29,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(308,29,'42',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(309,30,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(310,30,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(311,30,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(312,30,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(313,30,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(314,30,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(315,30,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(316,30,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(317,30,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(318,30,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(319,30,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(320,31,'Regular',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(321,31,'Adjustable',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(322,32,'Small (24-28)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(323,32,'Medium (30-34)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(324,32,'Large (36-40)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(325,33,'2',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(326,33,'3',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(327,33,'4',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(328,33,'5',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(329,33,'6',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(330,33,'7',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(331,34,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(332,34,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(333,34,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(334,34,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(335,34,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(336,34,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(337,34,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(338,34,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(339,34,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(340,35,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(341,35,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(342,35,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(343,35,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(344,35,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(345,35,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(346,35,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(347,35,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(348,35,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(349,35,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(350,35,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(351,36,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(352,36,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(353,36,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(354,36,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(355,36,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(356,36,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(357,36,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(358,36,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(359,36,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(360,36,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(361,36,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(362,37,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(363,37,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(364,37,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(365,37,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(366,37,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(367,37,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(368,37,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(369,37,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(370,37,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(371,37,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(372,37,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(373,38,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(374,38,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(375,38,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(376,38,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(377,38,'28',NULL,NULL,NULL,48,1,'2026-11-19 11:44:19','2026-11-24 09:04:03'),(378,38,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(379,38,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(380,38,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(381,38,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(382,38,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(383,38,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(384,39,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(385,39,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(386,39,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(387,39,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(388,39,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(389,39,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(390,39,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(391,39,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(392,39,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(393,39,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(394,39,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(395,40,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(396,40,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(397,40,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(398,40,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(399,40,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(400,40,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(401,40,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(402,40,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(403,40,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(404,40,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(405,40,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(406,41,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(407,41,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(408,41,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(409,41,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(410,41,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(411,41,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(412,41,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(413,41,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(414,41,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(415,41,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(416,41,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(417,42,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(418,42,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(419,42,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(420,42,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(421,42,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(422,42,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(423,42,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(424,42,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(425,42,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(426,42,'42',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(427,43,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(428,43,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(429,43,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(430,43,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(431,43,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(432,43,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(433,43,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(434,43,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(435,43,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(436,43,'42',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(437,44,'Regular',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(438,44,'Adjustable',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(439,45,'Small (24-28)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(440,45,'Medium (30-34)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(441,45,'Large (36-40)',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(442,46,'2',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(443,46,'3',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(444,46,'4',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(445,46,'5',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(446,46,'6',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(447,46,'7',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(448,47,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(449,47,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(450,47,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(451,47,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(452,47,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(453,47,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(454,47,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(455,47,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(456,47,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(457,48,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(458,48,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(459,48,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(460,48,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(461,48,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(462,48,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(463,48,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(464,48,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(465,48,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(466,48,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(467,48,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(468,49,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(469,49,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(470,49,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(471,49,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(472,49,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(473,49,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(474,49,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(475,49,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(476,49,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(477,49,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(478,49,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(479,50,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(480,50,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(481,50,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(482,50,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(483,50,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(484,50,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(485,50,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(486,50,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(487,50,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(488,50,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(489,50,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(490,51,'20',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(491,51,'22',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(492,51,'24',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(493,51,'26',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(494,51,'28',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(495,51,'30',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(496,51,'32',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(497,51,'34',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(498,51,'36',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(499,51,'38',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19'),(500,51,'40',NULL,NULL,NULL,50,1,'2026-11-19 11:44:19','2026-11-19 11:44:19');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `sku` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `actual_price` decimal(10,2) DEFAULT NULL,
  `vendor_commission` decimal(10,2) DEFAULT NULL,
  `vendor_price` decimal(10,2) DEFAULT NULL,
  `partner_percentage` decimal(5,2) NOT NULL DEFAULT 30.00,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT 1,
  `is_kit` tinyint(1) NOT NULL DEFAULT 0,
  `featured_image` varchar(255) DEFAULT NULL,
  `size_chart_image` varchar(255) DEFAULT NULL,
  `available_sizes` varchar(255) DEFAULT NULL,
  `gender` enum('all','boy','girl') NOT NULL DEFAULT 'all',
  `uniform_group` enum('academic','sports','accessories') NOT NULL DEFAULT 'academic',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_cat` (`category_id`),
  KEY `idx_products_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'White T-Shirt','white-t-shirt','OB-TEE-WHT','High quality combed cotton white daily academic t-shirt with reinforced collar.','Classic white regular-fit school activity t-shirt.',399.00,279.30,119.70,279.30,30.00,399.00,1,0,'assets/images/products/White-Tshirt.webp','assets/images/products/tshirt.png','18,20,22,24,26,28,30,32,34,36,38,40,42,44,46','all','academic',1,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(2,1,'Born White T-Shirts','bone-white-t-shirt','OB-TEE-BNW','Regular uniform born white t-shirt engineered for daily wear and easy care.','Off-white active school t-shirt.',420.00,294.00,126.00,294.00,30.00,420.00,1,0,'assets/images/products/Born-White-T-Shirts.webp','assets/images/products/tshirt.png','18,20,22,24,26,28,30,32,34,36,38,40,42,44,46','all','academic',2,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(3,1,'Green House T-Shirt','green-t-shirt','OB-TEE-GRN','Official Emerald Green sports house uniform t-shirt with breathable pique knit.','Emerald green house sports t-shirt.',399.00,279.30,119.70,279.30,30.00,399.00,1,0,'assets/images/products/Green.png','assets/images/products/tshirt.png','18,20,22,24,26,28,30,32,34,36,38,40,42,44,46','all','sports',3,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(4,1,'Blue House T-Shirt','blue-t-shirt','OB-TEE-BLU','Official Sapphire Blue sports house uniform t-shirt with colorfast dye.','Royal blue athletic school t-shirt.',399.00,279.30,119.70,279.30,30.00,399.00,1,0,'assets/images/products/Blue.webp','assets/images/products/tshirt.png','18,20,22,24,26,28,30,32,34,36,38,40,42,44,46','all','sports',4,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(5,1,'Red House T-Shirt','red-t-shirt','OB-TEE-RED','Official Ruby Red sports house uniform t-shirt crafted for athletic comfort.','Scarlet red athletic house t-shirt.',399.00,279.30,119.70,279.30,30.00,399.00,1,0,'assets/images/products/Red.webp','assets/images/products/tshirt.png','18,20,22,24,26,28,30,32,34,36,38,40,42,44,46','all','sports',5,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(6,1,'Yellow House T-Shirt','yellow-t-shirt','OB-TEE-YEL','Official Amber Yellow sports house uniform t-shirt with moisture-wicking weave.','Bright yellow active house t-shirt.',399.00,279.30,119.70,279.30,30.00,399.00,1,0,'assets/images/products/yellow.webp','assets/images/products/tshirt.png','18,20,22,24,26,28,30,32,34,36,38,40,42,44,46','all','sports',6,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(7,1,'Track Pants','track-pants','OB-TRK-PNT','Durable sports track pants with elastic waistband, side piping, and deep pockets.','Durable navy sports track pants.',580.00,406.00,174.00,406.00,30.00,580.00,1,0,'assets/images/products/Track-Pants.webp','assets/images/products/pants.png','20,22,24,26,28,30,32,34,36,38,40,42,44','all','academic',7,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(8,1,'Shorts','shorts','OB-SHT-NVY','Athletic sports shorts tailored with breathable poly-cotton for unrestricted movement.','',350.00,245.00,105.00,245.00,30.00,350.00,1,1,'assets/images/products/Shorts.webp','assets/images/products/shorts.png','20,22,24,26,28,30,32,34,36','boy','academic',8,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(9,3,'Socks','socks-pack-3','OB-SCK-WHT','Cushioned cotton school socks with ribbed stay-up cuff and reinforced heel.','Cotton cushioned school uniform socks.',220.00,154.00,66.00,154.00,30.00,220.00,1,0,'assets/images/products/Socks.webp','assets/images/products/socks.png','2,3,4,5,6,7','all','accessories',9,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(10,1,'Bottle Green Hoodies','bottle-green-hoodie','OB-HOD-GRN','Premium fleece winter hoodie in bottle green with kangaroo pocket and soft inner lining.','Cozy school winter hooded sweatshirt.',950.00,665.00,285.00,665.00,30.00,950.00,1,0,'assets/images/products/Bottle-Green-Hoddies.webp',NULL,'S,M,L,XL,XXL','all','academic',10,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(11,2,'International School Complete Kit','tis-complete-kit','OB-KIT-TIS01','All-in-one approved student uniform kit including regular wear, house t-shirt, track pants, and socks.','Head-to-toe complete school kit package.',2450.00,1715.00,735.00,1715.00,30.00,2450.00,0,1,'assets/images/home/593.jpg',NULL,'Standard Bundle','all','academic',11,1,'2026-11-18 06:20:35','2026-11-23 07:39:02'),(12,1,'GPS Brookes Grey Polo Uniform','gps-brookes-grey-polo-uniform-16','GPS-BRK-POLO','Approved GPS Brookes grey pique polo uniform with contrast collar and certified institutional stitching.','Approved GPS Brookes grey pique polo uniform with contrast collar and certified institutional stitching.',410.00,287.00,123.00,287.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(13,1,'GPS Brookes Grey Shorts','gps-brookes-grey-shorts-16','GPS-BRK-SHT','Durable institutional grey shorts with adjustable elastic waist and reinforced pockets.','Durable institutional grey shorts with adjustable elastic waist and reinforced pockets.',350.00,245.00,105.00,245.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey  Shorts.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36','boy','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(14,1,'Girls Brookes Pinafore','girls-brookes-pinafore-16','GPS-BRK-PIN','Official pleated girls pinafore tailored to certified GPS Brookes specifications.','Official pleated girls pinafore tailored to certified GPS Brookes specifications.',550.00,385.00,165.00,385.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Girls brookes pinafore.jpeg',NULL,'22, 24, 26, 28, 30, 32, 34, 36, 38','girl','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(15,1,'White Uniform Shirt','white-uniform-shirt-16','GPS-BRK-WSHIRT','Formal white cotton uniform shirt with structured collar and embroidered crest emblem.','Formal white cotton uniform shirt with structured collar and embroidered crest emblem.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/White Uniform Shirt.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(16,1,'GPS Sports Uniform Cap','gps-sports-uniform-cap-16','GPS-BRK-CAP','Protective sun visor sports cap with embroidered GPS insignia.','Protective sun visor sports cap with embroidered GPS insignia.',180.00,126.00,54.00,126.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/GPS Sports Uniform Cap.png',NULL,'Regular, Adjustable','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(17,1,'GPS Uniform Belt with Gunmetal Buckle','gps-uniform-belt-with-gunmetal-buckle-16','GPS-BRK-BELT','High-strength braided nylon belt with gunmetal quick-lock buckle.','High-strength braided nylon belt with gunmetal quick-lock buckle.',160.00,112.00,48.00,112.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Belt/GPS Uniform Belt with Gunmetal Buckle.png',NULL,'Small (24-28), Medium (30-34), Large (36-40)','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(18,1,'Charcoal Ribbed Crew Socks Pair','charcoal-ribbed-crew-socks-pair-16','GPS-BRK-SOCK','Cushioned combed cotton ribbed crew socks for daily academic comfort.','Cushioned combed cotton ribbed crew socks for daily academic comfort.',140.00,98.00,42.00,98.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Socks/Charcoal Ribbed Crew Socks Pair.png',NULL,'2, 3, 4, 5, 6, 7','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(19,1,'Sports Black Shorts','sports-black-shorts-16','GPS-BRK-SPO-SHT','Lightweight breathable quick-dry sports athletic shorts.','Lightweight breathable quick-dry sports athletic shorts.',350.00,245.00,105.00,245.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Black Shorts on Light Wood.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36','all','sports',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(20,1,'Blue House Sports V-Neck T-Shirt','blue-house-sports-v-neck-t-shirt-16','GPS-BRK-SPO-BLU','Official Blue House inter-house sports jersey with moisture-wicking technology.','Official Blue House inter-house sports jersey with moisture-wicking technology.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Blue V-Neck T-Shirt with “onbolt” Label.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(21,1,'Bright Green House Sports V-Neck T-Shirt','bright-green-house-sports-v-neck-t-shirt-16','GPS-BRK-SPO-GRN','Official Green House inter-house sports jersey with moisture-wicking technology.','Official Green House inter-house sports jersey with moisture-wicking technology.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Bright Green Onbolt GPS T-Shirt.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(22,1,'Red House Sports V-Neck T-Shirt','red-house-sports-v-neck-t-shirt-16','GPS-BRK-SPO-RED','Official Red House inter-house sports jersey with moisture-wicking technology.','Official Red House inter-house sports jersey with moisture-wicking technology.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Red V-Neck T-Shirt Mockup.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(23,1,'Yellow House Sports V-Neck T-Shirt','yellow-house-sports-v-neck-t-shirt-16','GPS-BRK-SPO-YEL','Official Yellow House inter-house sports jersey with moisture-wicking technology.','Official Yellow House inter-house sports jersey with moisture-wicking technology.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Yellow V-Neck T-Shirt Product Mockup.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(24,1,'GPS CBSE Grey Polo Uniform','gps-cbse-grey-polo-uniform-17','GPS-CBSE-POLO','Official GPS CBSE academic polo uniform with breathable micro-knit cotton.','Official GPS CBSE academic polo uniform with breathable micro-knit cotton.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(25,1,'GPS CBSE Grey Pinstripe Shorts','gps-cbse-grey-pinstripe-shorts-17','GPS-CBSE-SHT','Certified grey pinstripe shorts tailored for junior & middle school CBSE boys.','Certified grey pinstripe shorts tailored for junior & middle school CBSE boys.',350.00,245.00,105.00,245.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/GPS CBSE Grey Pinstripe Shorts.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36','boy','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(26,1,'Grey T-Shirt with Green Stripes','grey-t-shirt-with-green-stripes-17','GPS-CBSE-TEE-GRN','Grey activity t-shirt featuring distinctive GPS forest green shoulder accents.','Grey activity t-shirt featuring distinctive GPS forest green shoulder accents.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/grey tshirt green strips.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(27,1,'Girls Pinafore (CBSE)','girls-pinafore-cbse--17','GPS-CBSE-PIN','Official pleated pinafore dress designed for GPS CBSE primary students.','Official pleated pinafore dress designed for GPS CBSE primary students.',550.00,385.00,165.00,385.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/Girls Pinafore.jpeg',NULL,'22, 24, 26, 28, 30, 32, 34, 36, 38','girl','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(28,1,'Trousers (Boys)','trousers-boys--17','GPS-CBSE-TRS-B','Formal tailored school trousers with double-stitch hem and zip fly.','Formal tailored school trousers with double-stitch hem and zip fly.',580.00,406.00,174.00,406.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/Trousers Boys.png',NULL,'24, 26, 28, 30, 32, 34, 36, 38, 40, 42','boy','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(29,1,'Trousers (Girls)','trousers-girls--17','GPS-CBSE-TRS-G','Comfortable tailored trousers for senior girls with side pockets and belt loops.','Comfortable tailored trousers for senior girls with side pockets and belt loops.',580.00,406.00,174.00,406.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/trousers girls.png',NULL,'24, 26, 28, 30, 32, 34, 36, 38, 40, 42','girl','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(30,1,'White Uniform Shirt (CBSE)','white-uniform-shirt-cbse--17','GPS-CBSE-WSHIRT','Crisp institutional white uniform shirt made of durable easy-care poly-cotton.','Crisp institutional white uniform shirt made of durable easy-care poly-cotton.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/White Uniform Shirt.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(31,1,'GPS Sports Uniform Cap','gps-sports-uniform-cap-17','GPS-CBSE-CAP','Official protective sun visor cap for sports and physical education.','Official protective sun visor cap for sports and physical education.',180.00,126.00,54.00,126.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/GPS Sports Uniform Cap.png',NULL,'Regular, Adjustable','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(32,1,'GPS Uniform Belt with Gunmetal Buckle','gps-uniform-belt-with-gunmetal-buckle-17','GPS-CBSE-BELT','High-strength braided nylon belt with gunmetal quick-lock buckle.','High-strength braided nylon belt with gunmetal quick-lock buckle.',160.00,112.00,48.00,112.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Belt/GPS Uniform Belt with Gunmetal Buckle.png',NULL,'Small (24-28), Medium (30-34), Large (36-40)','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(33,1,'Charcoal Ribbed Crew Socks Pair','charcoal-ribbed-crew-socks-pair-17','GPS-CBSE-SOCK','Soft elasticated ribbed crew socks designed for all-day campus comfort.','Soft elasticated ribbed crew socks designed for all-day campus comfort.',140.00,98.00,42.00,98.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Socks/Charcoal Ribbed Crew Socks Pair.png',NULL,'2, 3, 4, 5, 6, 7','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(34,1,'Sports Black Shorts','sports-black-shorts-17','GPS-CBSE-SPO-SHT','Lightweight athletic black shorts with drawstring waistband.','Lightweight athletic black shorts with drawstring waistband.',350.00,245.00,105.00,245.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Black Shorts on Light Wood.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(35,1,'Blue House Sports V-Neck T-Shirt','blue-house-sports-v-neck-t-shirt-17','GPS-CBSE-SPO-BLU','Blue House sports jersey featuring breathable active-dry fabric.','Blue House sports jersey featuring breathable active-dry fabric.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Blue V-Neck T-Shirt with “onbolt” Label.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(36,1,'Bright Green House Sports V-Neck T-Shirt','bright-green-house-sports-v-neck-t-shirt-17','GPS-CBSE-SPO-GRN','Green House sports jersey featuring breathable active-dry fabric.','Green House sports jersey featuring breathable active-dry fabric.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Bright Green Onbolt GPS T-Shirt.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(37,1,'Red House Sports V-Neck T-Shirt','red-house-sports-v-neck-t-shirt-17','GPS-CBSE-SPO-RED','Red House sports jersey featuring breathable active-dry fabric.','Red House sports jersey featuring breathable active-dry fabric.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Red V-Neck T-Shirt Mockup.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(38,1,'Yellow House Sports V-Neck T-Shirt','yellow-house-sports-v-neck-t-shirt-17','GPS-CBSE-SPO-YEL','Yellow House sports jersey featuring breathable active-dry fabric.','Yellow House sports jersey featuring breathable active-dry fabric.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Yellow V-Neck T-Shirt Product Mockup.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(39,1,'Light Grey Onbolt Polo Shirt','light-grey-onbolt-polo-shirt-18','GPS-INT-POLO-DSP','Premium light grey OnBolt polo uniform with fine knit collar for International curriculum.','Premium light grey OnBolt polo uniform with fine knit collar for International curriculum.',420.00,294.00,126.00,294.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/INTERNATIONAL/Light Grey Onbolt Polo Shirt Product Display.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(40,1,'Light Grey Polo with GPS Badge','light-grey-polo-with-gps-badge-18','GPS-INT-POLO-BDG','Official GPS International Polo with embroidered school crest emblem.','Official GPS International Polo with embroidered school crest emblem.',420.00,294.00,126.00,294.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/INTERNATIONAL/Light Grey Polo with GPS Badge.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-23 07:39:02'),(41,1,'White Uniform Shirt (International)','white-uniform-shirt-international--18','GPS-INT-WSHIRT','Premium formal white uniform shirt with precision tailored stitching.','Premium formal white uniform shirt with precision tailored stitching.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/White Uniform Shirt.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','academic',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(42,1,'Trousers (Boys - International)','trousers-boys---international--18','GPS-INT-TRS-B','Tailored charcoal formal trousers with comfort waistband for boys.','Tailored charcoal formal trousers with comfort waistband for boys.',580.00,406.00,174.00,406.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/Trousers Boys.png',NULL,'24, 26, 28, 30, 32, 34, 36, 38, 40, 42','boy','academic',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(43,1,'Trousers (Girls - International)','trousers-girls---international--18','GPS-INT-TRS-G','Tailored charcoal formal trousers with comfort waistband for girls.','Tailored charcoal formal trousers with comfort waistband for girls.',580.00,406.00,174.00,406.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/CBSE/trousers girls.png',NULL,'24, 26, 28, 30, 32, 34, 36, 38, 40, 42','girl','academic',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(44,1,'GPS Sports Uniform Cap','gps-sports-uniform-cap-18','GPS-INT-CAP','Official protective sun visor cap for sports and outdoor athletics.','Official protective sun visor cap for sports and outdoor athletics.',180.00,126.00,54.00,126.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/GPS Sports Uniform Cap.png',NULL,'Regular, Adjustable','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(45,1,'GPS Uniform Belt with Gunmetal Buckle','gps-uniform-belt-with-gunmetal-buckle-18','GPS-INT-BELT','Official heavy-duty uniform belt with laser-engraved gunmetal clasp.','Official heavy-duty uniform belt with laser-engraved gunmetal clasp.',160.00,112.00,48.00,112.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Belt/GPS Uniform Belt with Gunmetal Buckle.png',NULL,'Small (24-28), Medium (30-34), Large (36-40)','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(46,1,'Charcoal Ribbed Crew Socks Pair','charcoal-ribbed-crew-socks-pair-18','GPS-INT-SOCK','Comfortable ribbed crew socks tailored for daily academic wear.','Comfortable ribbed crew socks tailored for daily academic wear.',140.00,98.00,42.00,98.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Socks/Charcoal Ribbed Crew Socks Pair.png',NULL,'2, 3, 4, 5, 6, 7','all','accessories',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(47,1,'Sports Black Shorts','sports-black-shorts-18','GPS-INT-SPO-SHT','Lightweight performance sports shorts with athletic fit.','Lightweight performance sports shorts with athletic fit.',350.00,245.00,105.00,245.00,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Black Shorts on Light Wood.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(48,1,'Blue House Sports V-Neck T-Shirt','blue-house-sports-v-neck-t-shirt-18','GPS-INT-SPO-BLU','Official Blue House jersey tailored for high-movement athletics.','Official Blue House jersey tailored for high-movement athletics.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Blue V-Neck T-Shirt with “onbolt” Label.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(49,1,'Bright Green House Sports V-Neck T-Shirt','bright-green-house-sports-v-neck-t-shirt-18','GPS-INT-SPO-GRN','Official Green House jersey tailored for high-movement athletics.','Official Green House jersey tailored for high-movement athletics.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Bright Green Onbolt GPS T-Shirt.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(50,1,'Red House Sports V-Neck T-Shirt','red-house-sports-v-neck-t-shirt-18','GPS-INT-SPO-RED','Official Red House jersey tailored for high-movement athletics.','Official Red House jersey tailored for high-movement athletics.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Red V-Neck T-Shirt Mockup.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46'),(51,1,'Yellow House Sports V-Neck T-Shirt','yellow-house-sports-v-neck-t-shirt-18','GPS-INT-SPO-YEL','Official Yellow House jersey tailored for high-movement athletics.','Official Yellow House jersey tailored for high-movement athletics.',399.00,279.30,119.70,279.30,30.00,NULL,1,0,'assets/images/schools/global-public-school/BROOKES/Sports/Yellow V-Neck T-Shirt Product Mockup.png',NULL,'20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40','all','sports',0,1,'2026-11-19 11:44:19','2026-11-24 14:12:46');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_role` (`role_id`),
  KEY `idx_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(2,5),(2,6),(2,7),(2,9),(7,2),(7,5),(7,7),(7,9),(7,11),(7,12),(7,13),(8,7),(8,9),(8,11);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Superadmin','superadmin','Unrestricted superadmin access to all website features, schools, and settings','2026-11-18 06:20:35','2026-11-23 07:39:02'),(2,'School Administrator','school_admin','Management access limited to assigned school products, orders and parents','2026-11-18 06:20:35','2026-11-18 06:20:35'),(3,'Parent','parent','Ordering portal access for enrolled student','2026-11-18 06:20:35','2026-11-18 06:20:35'),(4,'Software Admin','software_admin','Operational and financial administrative control (Orders, Reports, Catalog, Partner Splits) excluding technical system settings','2026-11-23 05:40:33','2026-11-23 05:40:33'),(5,'Software User','software_user','Front-line staff operational access to orders and reports. Cannot execute direct refunds without admin approval.','2026-11-23 05:40:33','2026-11-23 05:40:33'),(6,'School User','school_user','School-level portal access restricted strictly to assigned school orders and reports.','2026-11-23 05:40:33','2026-11-23 05:40:33'),(7,'Admin','admin','Administrative control over orders, schools, catalog, and partner reports','2026-11-23 07:39:02','2026-11-23 07:39:02'),(8,'School','school','School-level portal access strictly to assigned school orders, reports, and catalog','2026-11-23 07:39:02','2026-11-23 07:39:02');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_products`
--

DROP TABLE IF EXISTS `school_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `custom_price` decimal(10,2) DEFAULT NULL,
  `actual_price` decimal(10,2) DEFAULT NULL,
  `vendor_commission` decimal(10,2) DEFAULT NULL,
  `vendor_price` decimal(10,2) DEFAULT NULL,
  `partner_percentage` decimal(5,2) NOT NULL DEFAULT 30.00,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `applicable_grades` varchar(255) NOT NULL DEFAULT 'All Classes',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sp_school_prod` (`school_id`,`product_id`),
  KEY `fk_sp_product` (`product_id`),
  CONSTRAINT `fk_sp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=699 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_products`
--

LOCK TABLES `school_products` WRITE;
/*!40000 ALTER TABLE `school_products` DISABLE KEYS */;
INSERT INTO `school_products` VALUES (1,1,1,399.00,279.30,119.70,279.30,30.00,1,'All Classes',1,'2026-11-18 06:20:35'),(2,1,2,420.00,294.00,126.00,294.00,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(3,1,3,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(4,1,4,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(5,1,5,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(6,1,6,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(7,1,7,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-18 06:20:35'),(8,1,8,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-18 06:20:35'),(9,1,9,220.00,154.00,66.00,154.00,30.00,1,'All Classes',1,'2026-11-18 06:20:35'),(10,1,10,950.00,665.00,285.00,665.00,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(11,1,11,2450.00,1715.00,735.00,1715.00,30.00,0,'All Classes',1,'2026-11-18 06:20:35'),(254,16,12,410.00,287.00,123.00,287.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(255,16,13,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(256,16,14,550.00,385.00,165.00,385.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(257,16,15,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(258,16,16,180.00,126.00,54.00,126.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(259,16,17,160.00,112.00,48.00,112.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(260,16,18,140.00,98.00,42.00,98.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(261,16,19,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(262,16,20,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(263,16,21,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(264,16,22,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(265,16,23,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(266,17,24,399.00,279.30,119.70,279.30,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(267,17,25,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(268,17,26,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(269,17,27,550.00,385.00,165.00,385.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(270,17,28,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(271,17,29,580.00,406.00,174.00,406.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(272,17,30,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(273,17,31,180.00,126.00,54.00,126.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(274,17,32,160.00,112.00,48.00,112.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(275,17,33,140.00,98.00,42.00,98.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(276,17,34,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(277,17,35,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(278,17,36,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(279,17,37,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(280,17,38,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(281,18,39,420.00,294.00,126.00,294.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(282,18,40,420.00,294.00,126.00,294.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(283,18,41,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(284,18,42,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(285,18,43,580.00,406.00,174.00,406.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(286,18,44,180.00,126.00,54.00,126.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(287,18,45,160.00,112.00,48.00,112.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(288,18,46,140.00,98.00,42.00,98.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(289,18,47,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(290,18,48,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(291,18,49,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(292,18,50,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(293,18,51,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(295,19,12,410.00,287.00,123.00,287.00,30.00,1,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(296,19,13,350.00,245.00,105.00,245.00,30.00,1,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(297,19,14,550.00,385.00,165.00,385.00,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(298,19,15,399.00,279.30,119.70,279.30,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(299,19,16,180.00,126.00,54.00,126.00,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(300,19,17,160.00,112.00,48.00,112.00,30.00,1,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(301,19,18,140.00,98.00,42.00,98.00,30.00,1,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(302,19,19,350.00,245.00,105.00,245.00,30.00,1,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(303,19,20,399.00,279.30,119.70,279.30,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(304,19,21,399.00,279.30,119.70,279.30,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(305,19,22,399.00,279.30,119.70,279.30,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(306,19,23,399.00,279.30,119.70,279.30,30.00,0,'Playgroup, Pre-KG, LKG, UKG',1,'2026-11-19 12:23:19'),(563,28,1,399.00,279.30,119.70,279.30,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(564,28,2,420.00,294.00,126.00,294.00,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(565,28,3,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(566,28,4,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(567,28,5,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(568,28,6,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(569,28,7,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(570,28,8,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(571,28,9,220.00,154.00,66.00,154.00,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(572,28,10,950.00,665.00,285.00,665.00,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(573,28,11,2450.00,1715.00,735.00,1715.00,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(574,29,1,399.00,279.30,119.70,279.30,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(575,29,2,420.00,294.00,126.00,294.00,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(576,29,3,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(577,29,4,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(578,29,5,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(579,29,6,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-23 05:40:32'),(580,29,7,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(581,29,8,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-23 05:40:32'),(582,29,9,220.00,154.00,66.00,154.00,30.00,1,'All Classes',1,'2026-11-23 05:40:33'),(583,29,10,950.00,665.00,285.00,665.00,30.00,0,'All Classes',1,'2026-11-23 05:40:33'),(584,29,11,2450.00,1715.00,735.00,1715.00,30.00,0,'All Classes',1,'2026-11-23 05:40:33'),(585,20,12,410.00,287.00,123.00,287.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(586,20,13,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(587,20,14,550.00,385.00,165.00,385.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(588,20,15,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(589,20,16,180.00,126.00,54.00,126.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(590,20,17,160.00,112.00,48.00,112.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(591,20,18,140.00,98.00,42.00,98.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(592,20,19,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(593,20,20,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(594,20,21,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(595,20,22,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(596,20,23,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(597,20,24,399.00,279.30,119.70,279.30,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(598,20,25,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(599,20,26,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(600,20,27,550.00,385.00,165.00,385.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(601,20,28,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(602,20,29,580.00,406.00,174.00,406.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(603,20,30,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(604,20,31,180.00,126.00,54.00,126.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(605,20,32,160.00,112.00,48.00,112.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(606,20,33,140.00,98.00,42.00,98.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(607,20,34,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(608,20,35,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(609,20,36,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(610,20,37,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(611,20,38,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(612,20,39,420.00,294.00,126.00,294.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(613,20,40,420.00,294.00,126.00,294.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(614,20,41,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(615,20,42,580.00,406.00,174.00,406.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(616,20,43,580.00,406.00,174.00,406.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(617,20,44,180.00,126.00,54.00,126.00,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(618,20,45,160.00,112.00,48.00,112.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(619,20,46,140.00,98.00,42.00,98.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(620,20,47,350.00,245.00,105.00,245.00,30.00,1,'All Classes',1,'2026-11-19 11:44:19'),(621,20,48,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(622,20,49,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(623,20,50,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19'),(624,20,51,399.00,279.30,119.70,279.30,30.00,0,'All Classes',1,'2026-11-19 11:44:19');
/*!40000 ALTER TABLE `school_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_settings`
--

DROP TABLE IF EXISTS `school_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_setting` (`school_id`,`setting_key`),
  CONSTRAINT `fk_school_settings_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_settings`
--

LOCK TABLES `school_settings` WRITE;
/*!40000 ALTER TABLE `school_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `school_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `has_sub_schools` tinyint(1) NOT NULL DEFAULT 0,
  `sub_school_label` varchar(100) DEFAULT 'School',
  `name` varchar(180) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `location` varchar(180) DEFAULT NULL,
  `city` varchar(100) DEFAULT 'Trivandrum',
  `cluster` varchar(100) NOT NULL DEFAULT 'TVPM (Trivandrum)',
  `state` varchar(100) DEFAULT 'Kerala',
  `pincode` varchar(20) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT '+91 9946925888',
  `contact_email` varchar(150) DEFAULT 'onboltconsumerindia@gmail.com',
  `whatsapp_number` varchar(50) DEFAULT '918848670739',
  `description` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `ordering_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `enable_class_selection` tinyint(1) NOT NULL DEFAULT 1,
  `uniform_mode` enum('common','class_wise') NOT NULL DEFAULT 'common',
  `applicable_classes` text DEFAULT NULL,
  `allow_offline_payment` tinyint(1) NOT NULL DEFAULT 1,
  `allow_online_payment` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schools_slug` (`slug`),
  UNIQUE KEY `uq_schools_code` (`code`),
  KEY `idx_schools_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
INSERT INTO `schools` VALUES (1,NULL,1,'School','Trivandrum International School','trivandrum-international-school','TIS','Edackode, Trivandrum','Edackode, Trivandrum','TVPM (Trivandrum)','Kerala','695582','+91 471 2618272','admissions@trins.org','918848670739','Premier international residential & day school providing holistic education from Kindergarten through Grade 12.','assets/images/lovable/lovable-icon.png','assets/images/breadcrumb-bg.webp',1,0,'common',NULL,1,0,1,1,'2026-11-18 06:20:35','2026-11-24 14:12:46'),(16,20,0,'School','GPS Brookes - Trivandrum (IB)','gps-brookes','GIB','Trivandrum','Trivandrum','TVPM (Trivandrum)','Kerala',NULL,'+91 9946925888','onboltconsumerindia@gmail.com','918848670739','Global Public School - Brookes Wing official uniform portal. Approved institutional apparel, certified patterns, and sports kits.','assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png','assets/images/home/Untitled-design-3.png',1,1,'common','Early Years (Pre-KG, LKG, UKG), Primary PYP (Grade 1-5), Middle MYP (Grade 6-10), Diploma DP (Grade 11-12)',1,0,20,1,'2026-11-19 08:02:02','2026-11-23 18:10:20'),(17,20,0,'School','Global Public School (CBSE)','global-public-school-cbse','GCB','Trivandrum','Trivandrum','TVPM (Trivandrum)','Kerala',NULL,'+91 9946925888','onboltconsumerindia@gmail.com','918848670739','Global Public School - CBSE Wing official uniform portal. Certified daily uniforms, academic wear, and sports house kits.','assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png','assets/images/home/Untitled-design-3.png',1,1,'common','Primary (Grade 1-5), Middle School (Grade 6-8), Secondary (Grade 9-10), Senior Secondary (Grade 11-12)',1,0,20,1,'2026-11-19 08:02:02','2026-11-24 14:12:46'),(18,20,0,'School','GPS International - Trivandrum (CAIE)','gps-international','GCA','Trivandrum','Trivandrum','TVPM (Trivandrum)','Kerala',NULL,'+91 9946925888','onboltconsumerindia@gmail.com','918848670739','Global Public School - International Wing official uniform portal. Modern institutional apparel, certified uniforms, and accessories.','assets/images/schools/global-public-school/INTERNATIONAL/Light Grey Polo with GPS Badge.png','assets/images/home/Untitled-design-3.png',1,1,'common','Stage 1-5 (Primary), Stage 6-8 (Lower Secondary), IGCSE (Grade 9-10), AS/A Level (Grade 11-12)',1,0,20,1,'2026-11-19 08:02:02','2026-11-23 18:10:20'),(19,20,0,'School','GPS Stepping Stones (Pre-School)','gps-stepping-stones','GPS','Trivandrum','Trivandrum','TVPM (Trivandrum)','Kerala','695001','+91 9946925888','onboltconsumerindia@gmail.com','918848670739','Official uniform portal for GPS Stepping Stones (Pre-School). Approved daily uniforms, play wear, and accessories.','assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png','assets/images/breadcrumb-bg.webp',1,1,'common','Playgroup, Pre-KG, LKG, UKG',1,1,1,1,'2026-11-19 12:22:51','2026-11-21 06:01:44'),(20,NULL,1,'Stream','Global Public School','global-public-school','GLO','Trivandrum','Trivandrum','TVPM (Trivandrum)','Kerala',NULL,'+91 9946925888','onboltconsumerindia@gmail.com','918848670739',NULL,NULL,NULL,1,1,'common',NULL,1,0,0,1,'2026-11-20 08:38:38','2026-11-23 18:10:20'),(25,NULL,1,'Grade','BAVANS','bavans','BAV','TVM','TVM','TVPM (Trivandrum)','Kerala',NULL,'1234578590','','918848670739','','','',1,0,'common','',1,0,0,1,'2026-11-20 11:55:32','2026-11-21 08:07:56'),(26,25,0,'School','GRADE 1-4','grade-1-4','BV1','TVM','TVM','TVPM (Trivandrum)','Kerala',NULL,'1234578590','','918848670739','','','',1,0,'common','',1,0,0,1,'2026-11-20 11:55:32','2026-11-23 18:10:20'),(27,25,0,'School','GRADE 5-8','grade-5-8','BV5','TVM','TVM','TVPM (Trivandrum)','Kerala',NULL,'1234578590','','918848670739','','','',1,0,'common','',1,0,0,1,'2026-11-20 11:55:32','2026-11-23 18:10:20'),(28,1,0,'School','TVM Int Main School','tvm-int-main-school','TIE','Edackode, Trivandrum','Edackode, Trivandrum','TVPM (Trivandrum)','Kerala',NULL,'+91 9946925888','onboltconsumerindia@gmail.com','918848670739',NULL,NULL,NULL,1,1,'common',NULL,1,0,0,1,'2026-11-23 05:40:32','2026-11-23 18:10:20'),(29,1,0,'School','TVM Int Sch, Kowdiar','tvm-int-sch-kowdiar','TIK','Kowdiar, Trivandrum','Kowdiar, Trivandrum','TVPM (Trivandrum)','Kerala',NULL,'+91 9946925888','onboltconsumerindia@gmail.com','918848670739',NULL,NULL,NULL,1,1,'common',NULL,1,0,0,1,'2026-11-23 05:40:32','2026-11-23 18:10:20');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipping_addresses`
--

DROP TABLE IF EXISTS `shipping_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_addresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `grade_class` varchar(50) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `alt_phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address_line` text NOT NULL,
  `pincode` varchar(20) NOT NULL,
  `city` varchar(100) DEFAULT 'Trivandrum',
  `state` varchar(100) NOT NULL DEFAULT 'Kerala',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sa_order` (`order_id`),
  CONSTRAINT `fk_sa_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_addresses`
--

LOCK TABLES `shipping_addresses` WRITE;
/*!40000 ALTER TABLE `shipping_addresses` DISABLE KEYS */;
INSERT INTO `shipping_addresses` VALUES (1,1,'Rajesh Kumar','Aarav Kumar','Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Trivandrum','Kerala','2026-11-18 06:28:48'),(2,2,'Rajesh Kumar','Aarav Kumar','Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Trivandrum','Kerala','2026-11-18 06:29:09'),(3,3,'Rajesh Kumar','Aarav Kumar','Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Trivandrum','Kerala','2026-11-18 06:40:32'),(4,4,'Rajesh Kumar','Aarav Kumar','Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Trivandrum','Kerala','2026-11-18 06:57:32'),(5,5,'Rajesh Kumar','Aarav Kumar','Class 5-A','9847012345','9847054321','rajesh.kumar@example.com','Villa 14, Green Valley Enclave, Kazhakkoottam','695582','Trivandrum','Kerala','2026-11-18 07:19:04');
/*!40000 ALTER TABLE `shipping_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `admission_no` varchar(80) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `grade_class` varchar(50) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_school_adm` (`school_id`,`admission_no`),
  KEY `idx_students_class` (`grade_class`),
  CONSTRAINT `fk_students_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,1,'TIS-ADM-501','Aarav Kumar','Class 5','A','Male',1,'2026-11-18 06:20:35','2026-11-18 06:20:35'),(2,1,'TIS-ADM-302','Ananya Nair','Class 3','B','Female',1,'2026-11-18 06:20:35','2026-11-18 06:20:35'),(3,1,'TIS-ADM-703','Rohan Menon','Class 7','C','Male',1,'2026-11-18 06:20:35','2026-11-18 06:20:35'),(7,1,'TIS2025-042','Aarav Kumar','Grade 5','B','Male',1,'2026-11-19 04:47:12','2026-11-19 04:47:12');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author_name` varchar(120) NOT NULL,
  `role_title` varchar(120) DEFAULT NULL,
  `company_or_school` varchar(150) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `review_text` text NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (4,'Brooklyn Simmons','School Administration','St. Mary\'s Educational Academy',5,'OnBolt delivered excellent quality uniforms and accessories for our students. The logo printing is sharp, and the products are durable. Their team was professional and ensured timely delivery.','assets/images/home/author-1-1.webp',1,1,'2026-11-18 12:02:32'),(5,'Dianne Russell','Client Partner','Trivandrum International School',5,'We ordered complete school kits, and the experience was smooth from design to delivery. Everything was well-organized, and the quality exceeded our expectations.','assets/images/home/author-2-1.webp',2,1,'2026-11-18 12:02:32'),(6,'Leslie Alexander','Educational Director','Guruchaitanya Vidyalaya',5,'Their customized backpacks and branded items were impressive. The designs were exactly what we wanted, and the finishing was premium. Highly recommended for schools and organizations.','assets/images/home/author-3-1.webp',3,1,'2026-11-18 12:02:32');
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `website_pages`
--

DROP TABLE IF EXISTS `website_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `website_pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) NOT NULL,
  `title` varchar(180) NOT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  `banner_title` varchar(180) DEFAULT NULL,
  `banner_subtitle` varchar(250) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wp_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `website_pages`
--

LOCK TABLES `website_pages` WRITE;
/*!40000 ALTER TABLE `website_pages` DISABLE KEYS */;
INSERT INTO `website_pages` VALUES (1,'home','Home','OnBolt | Custom School Uniforms & Corporate Branding','Trusted by 300+ schools across India for premium uniforms and merchandise.','Custom School & Brand Solutions','Premium Quality Guaranteed','Welcome to OnBolt.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(2,'about','About Us','About OnBolt | Leading Uniform & Corporate Branding Specialists','Learn about OnBolt commitment to textile excellence, precision fit, and institution partnerships.','About OnBolt','Crafting Identity Since Inception','OnBolt delivers complete scholastic and corporate identity solutions.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(3,'categories','Product Categories','Uniform & Kit Categories | OnBolt','Browse school uniforms, sports kits, custom logos, accessories, and corporate gifts.','Our Categories','Comprehensive Product Spectrum','Explore our comprehensive product lines.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(4,'portfolio','Our Portfolio','Portfolio & Gallery | OnBolt Client Showcase','Explore completed uniform and branding projects for top schools and corporate clients.','Our Portfolio','Featured Projects & Institutions','Excellence showcased through our institution collaborations.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(5,'schools','Schools We Serve','School Directory | OnBolt School Ordering','Select your school to access school-specific kits, sizing options, and parental ordering.','Schools We Serve','Partner Institutions','Dedicated portals for partner schools.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(6,'customised-gifts','Customised Gifts','Customised Gifts & Corporate Hampers | OnBolt','Personalised gifts, bespoke merchandise, and premium institutional hampers.','Customised Gifts','Thoughtful Personalised Products','Bespoke gifts tailored for your brand.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(7,'corporate-tshirts','Corporate T-Shirts','Corporate T-Shirts & Custom Apparel | OnBolt','High-grade bio-washed pique polos and round-neck corporate t-shirts with crest embroidery.','Corporate T-Shirts','Premium Business Activewear','Professional apparel engineered for modern teams.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23'),(8,'contact','Contact Us','Contact OnBolt | Customer Support & Wholesale Enquiries','Reach our uniform and branding consultants for wholesale orders, school partnerships, and portal queries.','Contact Us','We Would Love To Hear From You','Get in touch with OnBolt experts.',1,'2026-11-18 06:21:23','2026-11-18 06:21:23');
/*!40000 ALTER TABLE `website_pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `website_sections`
--

DROP TABLE IF EXISTS `website_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `website_sections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `section_group` varchar(60) NOT NULL,
  `section_key` varchar(80) NOT NULL,
  `label` varchar(120) NOT NULL,
  `subtitle` varchar(200) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(120) DEFAULT NULL,
  `button_text` varchar(80) DEFAULT NULL,
  `button_link` varchar(200) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ws_key` (`section_key`),
  KEY `idx_ws_group` (`section_group`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `website_sections`
--

LOCK TABLES `website_sections` WRITE;
/*!40000 ALTER TABLE `website_sections` DISABLE KEYS */;
INSERT INTO `website_sections` VALUES (1,'home','hero','// HIGH QUALITY GUARANTEED //','Trusted by 300+ Schools Across India','Custom School & Brand Solutions','From premium school uniforms and complete kits to custom branding and corporate merchandise, OnBolt delivers high-quality products trusted by 300+ schools.','assets/images/home/540-pixel-x-836-pixel-1.webp','ph-shield-check','School Order','/schools/',1,1,'2026-11-18 06:20:35','2026-11-18 06:20:35'),(2,'home','quality','// PRODUCT QUALITY & COMFORT //','Crafted For Daily Student Life','Breathable, Durable & Perfectly Fitted Uniforms - Tested 806','Every stitch is precision-engineered using skin-safe, fade-resistant yarns.','assets/images/home/630px-x-420px-Catogary-Uniforms-1.webp','','','',2,1,'2026-11-18 06:20:35','2026-11-18 06:39:42'),(3,'home','custom_branding','// CUSTOM BRANDING & DESIGN //','Institutional Pride in Every Detail','Signature Crests, Custom Weaves & Colors','We match exact Pantone school shades, formulate high-definition crest embroideries, and integrate custom identity badges tailored for your institution.','assets/images/logo/630px-x-420px-CUSTOM-LOGO-1.webp','ph-paint-brush','View Portfolio','/portfolio/',3,1,'2026-11-18 06:20:35','2026-11-24 14:12:45'),(4,'home','corporate_gifting','// CORPORATE MERCHANDISE //','Elevate Your Business Presence','Executive Gifting & Premium Apparel','Reward teams, welcome new employees, and imprint lasting brand impressions with custom-embroidered apparel, luxury hampers, and curated desk essentials.','assets/images/portfolio/1500-x-1500-pixel-Corporate-Gifting.webp','ph-gift','Corporate Solutions','/corporate-tshirts/',4,1,'2026-11-18 06:20:35','2026-11-24 14:12:45'),(5,'home','how_it_works','// HOW IT WORKS //','Effortless Three-Step Fulfillment','From Concept to Student Desks','1. Share Your Requirements: Consult our textile specialists for designs, fabric swatches and school guidelines. 2. Design & Precision Production: Prototyping, sizing runs, and batch fabrication in certified units. 3. Quality Check & Doorstep Delivery: Strict multi-point audit, packaged by student name, and shipped reliably.',NULL,'ph-gear-six','Get In Touch','/contact/',5,1,'2026-11-18 06:20:35','2026-11-18 06:20:35');
/*!40000 ALTER TABLE `website_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `website_settings`
--

DROP TABLE IF EXISTS `website_settings`;
/*!50001 DROP VIEW IF EXISTS `website_settings`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `website_settings` AS SELECT
 1 AS `id`,
  1 AS `setting_key`,
  1 AS `setting_value`,
  1 AS `setting_group`,
  1 AS `updated_at` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'onbolt_management'
--

--
-- Final view structure for view `categories`
--

/*!50001 DROP VIEW IF EXISTS `categories`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `categories` AS select `product_categories`.`id` AS `id`,`product_categories`.`name` AS `name`,`product_categories`.`slug` AS `slug`,`product_categories`.`description` AS `description`,`product_categories`.`image` AS `image`,`product_categories`.`icon` AS `icon`,'product' AS `type`,`product_categories`.`sort_order` AS `sort_order`,`product_categories`.`status` AS `status`,`product_categories`.`created_at` AS `created_at`,`product_categories`.`updated_at` AS `updated_at` from `product_categories` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `homepage_sections`
--

/*!50001 DROP VIEW IF EXISTS `homepage_sections`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `homepage_sections` AS select `website_sections`.`id` AS `id`,`website_sections`.`section_group` AS `section_group`,`website_sections`.`section_key` AS `section_key`,`website_sections`.`label` AS `label`,`website_sections`.`subtitle` AS `subtitle`,`website_sections`.`title` AS `title`,`website_sections`.`description` AS `description`,`website_sections`.`image` AS `image`,`website_sections`.`icon` AS `icon`,`website_sections`.`button_text` AS `button_text`,`website_sections`.`button_link` AS `button_link`,`website_sections`.`sort_order` AS `sort_order`,`website_sections`.`status` AS `status`,`website_sections`.`created_at` AS `created_at`,`website_sections`.`updated_at` AS `updated_at` from `website_sections` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `pages`
--

/*!50001 DROP VIEW IF EXISTS `pages`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `pages` AS select `website_pages`.`id` AS `id`,`website_pages`.`slug` AS `slug`,`website_pages`.`title` AS `title`,`website_pages`.`meta_title` AS `meta_title`,`website_pages`.`meta_description` AS `meta_description`,`website_pages`.`banner_title` AS `banner_title`,`website_pages`.`banner_subtitle` AS `banner_subtitle`,`website_pages`.`content` AS `content`,`website_pages`.`status` AS `status`,`website_pages`.`created_at` AS `created_at`,`website_pages`.`updated_at` AS `updated_at` from `website_pages` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `website_settings`
--

/*!50001 DROP VIEW IF EXISTS `website_settings`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `website_settings` AS select `application_settings`.`id` AS `id`,`application_settings`.`setting_key` AS `setting_key`,`application_settings`.`setting_value` AS `setting_value`,`application_settings`.`setting_group` AS `setting_group`,`application_settings`.`updated_at` AS `updated_at` from `application_settings` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-24 19:42:53
