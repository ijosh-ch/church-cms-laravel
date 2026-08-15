/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `action_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `action_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actionable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actionable_id` int unsigned NOT NULL,
  `target_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` int unsigned DEFAULT NULL,
  `fields` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `exception` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `original` text COLLATE utf8mb4_unicode_ci,
  `changes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `action_events_actionable_type_actionable_id_index` (`actionable_type`,`actionable_id`),
  KEY `action_events_batch_id_model_type_model_id_index` (`batch_id`,`model_type`,`model_id`),
  KEY `action_events_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int DEFAULT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` int DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` text COLLATE utf8mb4_unicode_ci,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `category` enum('prayer','education','meeting','culturals') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `is_present` tinyint(1) NOT NULL DEFAULT '0',
  `present_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendances_church_id_foreign` (`church_id`),
  KEY `attendances_user_id_foreign` (`user_id`),
  CONSTRAINT `attendances_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `attendances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `authentications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authentications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_on` timestamp NOT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `authentications_user_id_foreign` (`user_id`),
  CONSTRAINT `authentications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bible_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bible_books` (
  `book_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `english_book` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamil_book` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chapter_count` int NOT NULL,
  PRIMARY KEY (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bible_verses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bible_verses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `english_verse` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamil_verse` text COLLATE utf8mb4_unicode_ci,
  `book_id` int NOT NULL,
  `chapter_id` int NOT NULL,
  `verse_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bulletins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulletins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('week','month') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `week` int DEFAULT NULL,
  `month` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int DEFAULT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bulletins_church_id_foreign` (`church_id`),
  KEY `bulletins_created_by_foreign` (`created_by`),
  KEY `bulletins_updated_by_foreign` (`updated_by`),
  CONSTRAINT `bulletins_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `bulletins_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `bulletins_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  UNIQUE KEY `cache_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `mailinglist_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_church_id_foreign` (`church_id`),
  KEY `campaign_mailinglist_id_foreign` (`mailinglist_id`),
  CONSTRAINT `campaign_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `campaign_mailinglist_id_foreign` FOREIGN KEY (`mailinglist_id`) REFERENCES `mailinglists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_email`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_email` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `campaign_id` bigint unsigned NOT NULL,
  `email_id` bigint unsigned NOT NULL,
  `delay_in_days` int NOT NULL DEFAULT '0',
  `delay_in_hours` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_email_church_id_foreign` (`church_id`),
  KEY `campaign_email_campaign_id_foreign` (`campaign_id`),
  KEY `campaign_email_email_id_foreign` (`email_id`),
  CONSTRAINT `campaign_email_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaign` (`id`),
  CONSTRAINT `campaign_email_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `campaign_email_email_id_foreign` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `church`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `church` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` int unsigned DEFAULT NULL,
  `state_id` int unsigned DEFAULT NULL,
  `city_id` int unsigned DEFAULT NULL,
  `pincode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `slug` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `church_country_id_foreign` (`country_id`),
  KEY `church_state_id_foreign` (`state_id`),
  KEY `church_city_id_foreign` (`city_id`),
  CONSTRAINT `church_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `church_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `church_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `church_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `church_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `church_details_church_id_foreign` (`church_id`),
  CONSTRAINT `church_details_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `country_id` int unsigned DEFAULT NULL,
  `state_id` int unsigned DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_country_id_foreign` (`country_id`),
  KEY `cities_state_id_foreign` (`state_id`),
  CONSTRAINT `cities_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `cities_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `fullname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `query` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_submission` datetime NOT NULL,
  `properties` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_church_id_foreign` (`church_id`),
  CONSTRAINT `contacts_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tel_prefix` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `order` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_short_name_unique` (`short_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `gateway_ref` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` text COLLATE utf8mb4_unicode_ci,
  `uuid` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `donations_uuid_unique` (`uuid`),
  KEY `donations_church_id_foreign` (`church_id`),
  KEY `donations_user_id_foreign` (`user_id`),
  CONSTRAINT `donations_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `donations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reply_to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `variables` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emails_church_id_foreign` (`church_id`),
  CONSTRAINT `emails_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_attendance_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attendance_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `event_id` int unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `opened_by` int unsigned NOT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `locked_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_attendance_sessions_event_id_attendance_date_unique` (`event_id`,`attendance_date`),
  KEY `event_attendance_sessions_church_id_foreign` (`church_id`),
  KEY `event_attendance_sessions_opened_by_foreign` (`opened_by`),
  KEY `event_attendance_sessions_locked_by_foreign` (`locked_by`),
  CONSTRAINT `event_attendance_sessions_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `event_attendance_sessions_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_attendance_sessions_locked_by_foreign` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_attendance_sessions_opened_by_foreign` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attendees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint unsigned NOT NULL,
  `church_id` int unsigned NOT NULL,
  `event_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `scanned_at` timestamp NULL DEFAULT NULL,
  `scanned_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_attendees_session_id_user_id_unique` (`session_id`,`user_id`),
  KEY `event_attendees_church_id_foreign` (`church_id`),
  KEY `event_attendees_event_id_foreign` (`event_id`),
  KEY `event_attendees_user_id_foreign` (`user_id`),
  KEY `event_attendees_scanned_by_foreign` (`scanned_by`),
  CONSTRAINT `event_attendees_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `event_attendees_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_attendees_scanned_by_foreign` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_attendees_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `event_attendance_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_attendees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_galleries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_galleries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `event_id` int unsigned NOT NULL,
  `path` longtext COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_galleries_church_id_foreign` (`church_id`),
  KEY `event_galleries_event_id_foreign` (`event_id`),
  KEY `event_galleries_created_by_foreign` (`created_by`),
  KEY `event_galleries_updated_by_foreign` (`updated_by`),
  CONSTRAINT `event_galleries_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `event_galleries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `event_galleries_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `event_galleries_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_managers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_managers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_managers_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `event_managers_user_id_foreign` (`user_id`),
  CONSTRAINT `event_managers_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_managers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `select_type` enum('public','private','online') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `repeats` int DEFAULT '0',
  `freq` int DEFAULT '0',
  `freq_term` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `month_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `days_of_week` json DEFAULT NULL,
  `duration_minutes` smallint unsigned DEFAULT NULL,
  `location` text COLLATE utf8mb4_unicode_ci,
  `category` enum('prayer','education','meeting','culturals','sermon') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organised_by` longtext COLLATE utf8mb4_unicode_ci,
  `image` text COLLATE utf8mb4_unicode_ci,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `allDay` tinyint NOT NULL DEFAULT '0',
  `url` text COLLATE utf8mb4_unicode_ci,
  `publish_to_web` tinyint(1) NOT NULL DEFAULT '1',
  `enable_gallery` tinyint(1) NOT NULL DEFAULT '1',
  `enable_attendance` tinyint(1) NOT NULL DEFAULT '0',
  `attendance_scope` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `attendance_group_id` bigint unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_church_id_foreign` (`church_id`),
  KEY `events_created_by_foreign` (`created_by`),
  KEY `events_updated_by_foreign` (`updated_by`),
  CONSTRAINT `events_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `events_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faq_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faq_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faq_categories_church_id_foreign` (`church_id`),
  CONSTRAINT `faq_categories_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `faq_category_id` int unsigned NOT NULL,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faqs_church_id_foreign` (`church_id`),
  KEY `faqs_faq_category_id_foreign` (`faq_category_id`),
  CONSTRAINT `faqs_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `faqs_faq_category_id_foreign` FOREIGN KEY (`faq_category_id`) REFERENCES `faq_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `favorites_church_id_foreign` (`church_id`),
  KEY `favorites_user_id_foreign` (`user_id`),
  CONSTRAINT `favorites_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `favorites_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feedback_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feedback_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `feedback_id` int unsigned NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` longtext COLLATE utf8mb4_unicode_ci,
  `category` enum('bug','others','suggestion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_seen` enum('0','has_seen','action_taken') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `deleted_from_sender` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_from_receiver` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_messages_church_id_foreign` (`church_id`),
  KEY `feedback_messages_user_id_foreign` (`user_id`),
  KEY `feedback_messages_feedback_id_foreign` (`feedback_id`),
  CONSTRAINT `feedback_messages_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `feedback_messages_feedback_id_foreign` FOREIGN KEY (`feedback_id`) REFERENCES `feedbacks` (`id`),
  CONSTRAINT `feedback_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feedbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feedbacks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` int DEFAULT NULL,
  `mobile_no` int DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `admin_id` int unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedbacks_church_id_foreign` (`church_id`),
  KEY `feedbacks_user_id_foreign` (`user_id`),
  KEY `feedbacks_admin_id_foreign` (`admin_id`),
  CONSTRAINT `feedbacks_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  CONSTRAINT `feedbacks_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `feedbacks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `funds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `payaccount_id` int unsigned DEFAULT NULL,
  `authorised_by` int unsigned DEFAULT NULL,
  `authorised_at` datetime DEFAULT NULL,
  `membership` enum('guest','member') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `data` longtext COLLATE utf8mb4_unicode_ci,
  `amount` double NOT NULL,
  `method` enum('bank','card','cash','cheque','demanddraft') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_details` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('request','pending','deposited','cancel') COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` longtext COLLATE utf8mb4_unicode_ci,
  `attachment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `funds_church_id_foreign` (`church_id`),
  KEY `funds_payaccount_id_foreign` (`payaccount_id`),
  KEY `funds_authorised_by_foreign` (`authorised_by`),
  KEY `funds_user_id_foreign` (`user_id`),
  CONSTRAINT `funds_authorised_by_foreign` FOREIGN KEY (`authorised_by`) REFERENCES `users` (`id`),
  CONSTRAINT `funds_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `funds_payaccount_id_foreign` FOREIGN KEY (`payaccount_id`) REFERENCES `payaccounts` (`id`),
  CONSTRAINT `funds_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `galleries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `galleries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `galleries_church_id_foreign` (`church_id`),
  KEY `galleries_created_by_foreign` (`created_by`),
  KEY `galleries_updated_by_foreign` (`updated_by`),
  CONSTRAINT `galleries_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `galleries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `galleries_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `get_response`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `get_response` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_open_campaign_id` bigint unsigned DEFAULT NULL,
  `no_email_open_campaign_id` bigint unsigned DEFAULT NULL,
  `day_after` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `get_response_church_id_foreign` (`church_id`),
  KEY `get_response_campaign_id_foreign` (`campaign_id`),
  KEY `get_response_email_open_campaign_id_foreign` (`email_open_campaign_id`),
  KEY `get_response_no_email_open_campaign_id_foreign` (`no_email_open_campaign_id`),
  CONSTRAINT `get_response_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaign` (`id`) ON DELETE CASCADE,
  CONSTRAINT `get_response_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `get_response_email_open_campaign_id_foreign` FOREIGN KEY (`email_open_campaign_id`) REFERENCES `campaign` (`id`) ON DELETE CASCADE,
  CONSTRAINT `get_response_no_email_open_campaign_id_foreign` FOREIGN KEY (`no_email_open_campaign_id`) REFERENCES `campaign` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `group_id` int unsigned NOT NULL,
  `role` enum('group_admin','member','guest') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_links_church_id_foreign` (`church_id`),
  KEY `group_links_user_id_foreign` (`user_id`),
  KEY `group_links_group_id_foreign` (`group_id`),
  CONSTRAINT `group_links_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `group_links_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `group_links_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `group_id` int unsigned NOT NULL,
  `title` longtext COLLATE utf8mb4_unicode_ci,
  `message` longtext COLLATE utf8mb4_unicode_ci,
  `attachments` longtext COLLATE utf8mb4_unicode_ci,
  `attachment_type` enum('image','video','url') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('inactive','active') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_posts_church_id_foreign` (`church_id`),
  KEY `group_posts_user_id_foreign` (`user_id`),
  KEY `group_posts_group_id_foreign` (`group_id`),
  CONSTRAINT `group_posts_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `group_posts_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  CONSTRAINT `group_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `group_type` enum('common_interests','everyone','married_couples','men','women','young_adults','youth') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `groups_church_id_foreign` (`church_id`),
  KEY `groups_category_id_foreign` (`category_id`),
  KEY `groups_created_by_foreign` (`created_by`),
  CONSTRAINT `groups_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `group_category` (`id`),
  CONSTRAINT `groups_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `groups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `helps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `helps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `contact_details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approve','reject','close') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `closed_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `helps_church_id_foreign` (`church_id`),
  KEY `helps_user_id_foreign` (`user_id`),
  KEY `helps_closed_by_foreign` (`closed_by`),
  CONSTRAINT `helps_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `helps_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `helps_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `keywords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keywords` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_queues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_queues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_id` bigint unsigned NOT NULL,
  `subscriber_id` bigint unsigned NOT NULL,
  `mailinglist_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reply_to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_mail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `fired_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `exception` text COLLATE utf8mb4_unicode_ci,
  `smtp` text COLLATE utf8mb4_unicode_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `clicks` bigint unsigned NOT NULL DEFAULT '0',
  `status` enum('sent','delivered','bounce','spam') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule_checked_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mailing_list_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailing_list_subscribers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mailing_list_id` bigint unsigned NOT NULL,
  `subscribers_id` bigint unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mailinglists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailinglists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `scope` enum('subscription','campaign','segment') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'subscription',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `slug` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mailinglists_name_unique` (`name`),
  KEY `mailinglists_church_id_foreign` (`church_id`),
  CONSTRAINT `mailinglists_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mailtemplates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailtemplates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` text COLLATE utf8mb4_unicode_ci,
  `mail_content` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `media_type` enum('audio','video','image') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('attach','record','upload','url') COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `media_files_church_id_foreign` (`church_id`),
  KEY `media_files_created_by_foreign` (`created_by`),
  KEY `media_files_updated_by_foreign` (`updated_by`),
  CONSTRAINT `media_files_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `media_files_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `media_files_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news_letters` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `news_letters_name_unique` (`name`),
  UNIQUE KEY `news_letters_email_unique` (`email`),
  KEY `news_letters_church_id_foreign` (`church_id`),
  CONSTRAINT `news_letters_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `notes` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `entity_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notes_church_id_foreign` (`church_id`),
  KEY `notes_created_by_foreign` (`created_by`),
  KEY `notes_updated_by_foreign` (`updated_by`),
  CONSTRAINT `notes_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `notes_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `client_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint NOT NULL,
  `client_id` int unsigned NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_personal_access_clients_client_id_index` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `attachment_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_attachments_page_id_foreign` (`page_id`),
  CONSTRAINT `page_attachments_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_categories_slug_unique` (`slug`),
  KEY `page_categories_church_id_foreign` (`church_id`),
  CONSTRAINT `page_categories_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_details` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `page_id` int unsigned NOT NULL,
  `is_following` tinyint(1) DEFAULT NULL,
  `like` tinyint(1) DEFAULT NULL,
  `dislike` tinyint(1) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_details_user_id_foreign` (`user_id`),
  KEY `page_details_page_id_foreign` (`page_id`),
  CONSTRAINT `page_details_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`),
  CONSTRAINT `page_details_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `layout_template` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saved_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_versions_page_id_version_number_index` (`page_id`,`version_number`),
  CONSTRAINT `page_versions_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `page_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_text` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_order` smallint unsigned NOT NULL DEFAULT '0',
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `layout_template` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left-sidebar',
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pages_church_id_foreign` (`church_id`),
  KEY `pages_category_id_foreign` (`category_id`),
  KEY `pages_created_by_foreign` (`created_by`),
  CONSTRAINT `pages_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `page_categories` (`id`),
  CONSTRAINT `pages_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `pages_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payaccounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payaccounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `paymentgateway_id` int unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `attachment` text COLLATE utf8mb4_unicode_ci,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `param1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param5` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param6` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param7` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `param8` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payaccounts_church_id_foreign` (`church_id`),
  KEY `payaccounts_paymentgateway_id_foreign` (`paymentgateway_id`),
  CONSTRAINT `payaccounts_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `payaccounts_paymentgateway_id_foreign` FOREIGN KEY (`paymentgateway_id`) REFERENCES `paymentgateways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paymentgateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paymentgateways` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `gatewayname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `displayname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paymentgateways_gatewayname_unique` (`gatewayname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `permission_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `permission_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `permission_user_permission_id_foreign` (`permission_id`),
  KEY `permission_user_user_id_foreign` (`user_id`),
  CONSTRAINT `permission_user_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  CONSTRAINT `permission_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `photos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `gallery_id` int unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photos_church_id_foreign` (`church_id`),
  KEY `photos_gallery_id_foreign` (`gallery_id`),
  KEY `photos_created_by_foreign` (`created_by`),
  KEY `photos_updated_by_foreign` (`updated_by`),
  CONSTRAINT `photos_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `photos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `photos_gallery_id_foreign` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`),
  CONSTRAINT `photos_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_categories_church_id_foreign` (`church_id`),
  CONSTRAINT `post_categories_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_comment_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_comment_details` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `post_comment_id` int unsigned NOT NULL,
  `like` tinyint(1) DEFAULT NULL,
  `unlike` tinyint(1) DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_comment_details_user_id_foreign` (`user_id`),
  KEY `post_comment_details_post_comment_id_foreign` (`post_comment_id`),
  CONSTRAINT `post_comment_details_post_comment_id_foreign` FOREIGN KEY (`post_comment_id`) REFERENCES `post_comments` (`id`),
  CONSTRAINT `post_comment_details_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_comments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `guest_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int NOT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comments` longtext COLLATE utf8mb4_unicode_ci,
  `attachment_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `public_like_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `post_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_details` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `post_id` int unsigned NOT NULL,
  `like` tinyint(1) DEFAULT NULL,
  `unlike` tinyint(1) DEFAULT NULL,
  `save` tinyint(1) DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_details_user_id_foreign` (`user_id`),
  KEY `post_details_post_id_foreign` (`post_id`),
  CONSTRAINT `post_details_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`),
  CONSTRAINT `post_details_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_tag` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tag_id` int unsigned NOT NULL,
  `post_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_tag_tag_id_foreign` (`tag_id`),
  KEY `post_tag_post_id_foreign` (`post_id`),
  CONSTRAINT `post_tag_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`),
  CONSTRAINT `post_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_created_at` timestamp NULL DEFAULT NULL,
  `is_posted` tinyint(1) NOT NULL DEFAULT '0',
  `posted_at` timestamp NULL DEFAULT NULL,
  `tag` int DEFAULT NULL,
  `status` enum('drafted','pending','posted','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_like_count` int unsigned NOT NULL DEFAULT '0',
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `posts_church_id_foreign` (`church_id`),
  KEY `posts_category_id_foreign` (`category_id`),
  KEY `posts_created_by_foreign` (`created_by`),
  CONSTRAINT `posts_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `post_categories` (`id`),
  CONSTRAINT `posts_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `posts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prayer_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prayer_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `css_class` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emoji` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#6366F1',
  `gradient_start` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#EEF2FF',
  `gradient_end` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#E0E7FF',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prayer_categories_church_id_name_unique` (`church_id`,`name`),
  KEY `prayer_categories_created_by_foreign` (`created_by`),
  KEY `prayer_categories_updated_by_foreign` (`updated_by`),
  KEY `prayer_categories_church_id_is_active_index` (`church_id`,`is_active`),
  KEY `prayer_categories_church_id_sort_order_index` (`church_id`,`sort_order`),
  CONSTRAINT `prayer_categories_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prayer_categories_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prayer_categories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prayer_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prayer_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `prayer_id` bigint unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `participant_type` enum('MEMBER','GUEST','ANONYMOUS') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MEMBER',
  `anon_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prayer_participants_prayer_id_user_id_unique` (`prayer_id`,`user_id`),
  KEY `prayer_participants_church_id_foreign` (`church_id`),
  KEY `prayer_participants_user_id_foreign` (`user_id`),
  KEY `prayer_participants_prayer_id_anon_hash_index` (`prayer_id`,`anon_hash`),
  CONSTRAINT `prayer_participants_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prayer_participants_prayer_id_foreign` FOREIGN KEY (`prayer_id`) REFERENCES `prayers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prayer_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prayers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prayers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_text` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('PENDING','ACTIVE','ANSWERED','ENDED','REJECTED','UNPUBLISHED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `rejected_by` int unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `should_delete_at` timestamp NULL DEFAULT NULL,
  `expiry_days` int DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `pinned_at` timestamp NULL DEFAULT NULL,
  `pinned_by` int unsigned DEFAULT NULL,
  `answer_testimony` text COLLATE utf8mb4_unicode_ci,
  `answered_by` int unsigned DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `member_count` int unsigned NOT NULL DEFAULT '0',
  `guest_count` int unsigned NOT NULL DEFAULT '0',
  `anonymous_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prayers_category_id_foreign` (`category_id`),
  KEY `prayers_user_id_foreign` (`user_id`),
  KEY `prayers_approved_by_foreign` (`approved_by`),
  KEY `prayers_rejected_by_foreign` (`rejected_by`),
  KEY `prayers_pinned_by_foreign` (`pinned_by`),
  KEY `prayers_answered_by_foreign` (`answered_by`),
  KEY `prayers_church_id_status_index` (`church_id`,`status`),
  KEY `prayers_church_id_category_id_status_index` (`church_id`,`category_id`,`status`),
  KEY `prayers_church_id_expires_at_status_index` (`church_id`,`expires_at`,`status`),
  KEY `prayers_church_id_pinned_at_index` (`church_id`,`pinned_at`),
  CONSTRAINT `prayers_answered_by_foreign` FOREIGN KEY (`answered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prayers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prayers_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `prayer_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prayers_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prayers_pinned_by_foreign` FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prayers_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prayers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` longtext COLLATE utf8mb4_unicode_ci,
  `tamil_quotes` longtext COLLATE utf8mb4_unicode_ci,
  `english_quotes` longtext COLLATE utf8mb4_unicode_ci,
  `publish_on` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotes_church_id_foreign` (`church_id`),
  KEY `quotes_user_id_foreign` (`user_id`),
  CONSTRAINT `quotes_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `quotes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reminder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminder` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `from` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `to` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via` enum('sms','mail','notification') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `queue_status` enum('queue','process','deliver','cancel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queue',
  `sms_response` longtext COLLATE utf8mb4_unicode_ci,
  `executed_at` date DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  `data` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reminder_church_id_foreign` (`church_id`),
  CONSTRAINT `reminder_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `role_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`,`user_type`),
  KEY `role_user_role_id_foreign` (`role_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `send_mail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `send_mail` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_address` text COLLATE utf8mb4_unicode_ci,
  `mode` enum('mail','notification','sms') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from` text COLLATE utf8mb4_unicode_ci,
  `to` text COLLATE utf8mb4_unicode_ci,
  `subject` text COLLATE utf8mb4_unicode_ci,
  `message` longtext COLLATE utf8mb4_unicode_ci,
  `attachments` longtext COLLATE utf8mb4_unicode_ci,
  `status` enum('queue','delivered','failed') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('mail','inbox','sent') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_id` text COLLATE utf8mb4_unicode_ci,
  `batch_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `is_executed` tinyint(1) NOT NULL DEFAULT '0',
  `fired_at` timestamp NULL DEFAULT NULL,
  `read_status` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `send_mail_church_id_foreign` (`church_id`),
  KEY `send_mail_user_id_foreign` (`user_id`),
  CONSTRAINT `send_mail_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `send_mail_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sermons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sermons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `title` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sermons_church_id_foreign` (`church_id`),
  KEY `sermons_user_id_foreign` (`user_id`),
  CONSTRAINT `sermons_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `sermons_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sermons_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sermons_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `sermons_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_link` text COLLATE utf8mb4_unicode_ci,
  `audio_link` text COLLATE utf8mb4_unicode_ci,
  `pdf_link` text COLLATE utf8mb4_unicode_ci,
  `date` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sermons_links_church_id_foreign` (`church_id`),
  KEY `sermons_links_user_id_foreign` (`user_id`),
  KEY `sermons_links_sermons_id_foreign` (`sermons_id`),
  KEY `sermons_links_created_by_foreign` (`created_by`),
  KEY `sermons_links_updated_by_foreign` (`updated_by`),
  CONSTRAINT `sermons_links_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `sermons_links_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `sermons_links_sermons_id_foreign` FOREIGN KEY (`sermons_id`) REFERENCES `sermons` (`id`),
  CONSTRAINT `sermons_links_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `sermons_links_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  UNIQUE KEY `sessions_id_unique` (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `field` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` text COLLATE utf8mb4_unicode_ci,
  `content` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `smtp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smtp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `port` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encryption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `smtp_church_id_foreign` (`church_id`),
  CONSTRAINT `smtp_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `country_id` int unsigned DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `states_country_id_foreign` (`country_id`),
  CONSTRAINT `states_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscribers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aff` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscribers_email_unique` (`email`),
  KEY `subscribers_church_id_foreign` (`church_id`),
  CONSTRAINT `subscribers_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_tag_name_unique` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_group_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `userprofiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `userprofiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `firstname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_firstname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_lastname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','transgender') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `was_baptized` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `baptism_date` date DEFAULT NULL,
  `profession` enum('admin','business','doctor','engineer','government_employee','home_maker','lawyer','pastor','police','professionals','self_employed','student','teacher','others','guest','preacher') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city_id` int unsigned DEFAULT NULL,
  `state_id` int unsigned DEFAULT NULL,
  `country_id` int unsigned DEFAULT NULL,
  `pincode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `membership_type` enum('member','guest') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `membership_start_date` date DEFAULT NULL,
  `membership_end_date` longtext COLLATE utf8mb4_unicode_ci,
  `family` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marriage_status` enum('single','married','ended_by_death','ended_by_divorce','separated') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marriage_start_date` date DEFAULT NULL,
  `marriage_end_date` date DEFAULT NULL,
  `aadhar_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` longtext COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','exit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `facebook_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userprofiles_church_id_foreign` (`church_id`),
  KEY `userprofiles_user_id_foreign` (`user_id`),
  KEY `userprofiles_city_id_foreign` (`city_id`),
  KEY `userprofiles_state_id_foreign` (`state_id`),
  KEY `userprofiles_country_id_foreign` (`country_id`),
  CONSTRAINT `userprofiles_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `userprofiles_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  CONSTRAINT `userprofiles_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `userprofiles_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `userprofiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned DEFAULT NULL,
  `usergroup_id` int unsigned DEFAULT NULL,
  `ref_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_country_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verification_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `is_reset` tinyint(1) NOT NULL DEFAULT '0',
  `platform_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_church_id_foreign` (`church_id`),
  KEY `users_usergroup_id_foreign` (`usergroup_id`),
  KEY `users_ref_id_foreign` (`ref_id`),
  CONSTRAINT `users_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `users_ref_id_foreign` FOREIGN KEY (`ref_id`) REFERENCES `users` (`id`),
  CONSTRAINT `users_usergroup_id_foreign` FOREIGN KEY (`usergroup_id`) REFERENCES `user_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `votes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `like` int NOT NULL DEFAULT '0',
  `unlike` int NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `votes_church_id_foreign` (`church_id`),
  KEY `votes_user_id_foreign` (`user_id`),
  CONSTRAINT `votes_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhooks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `verb` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POST',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mailinglist_id` bigint unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `handshake_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhooks_mailinglist_id_foreign` (`mailinglist_id`),
  CONSTRAINT `webhooks_mailinglist_id_foreign` FOREIGN KEY (`mailinglist_id`) REFERENCES `mailinglists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `widgets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `church_id` int unsigned NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home',
  `position` enum('top','bottom') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int unsigned NOT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `widgets_church_id_foreign` (`church_id`),
  KEY `widgets_created_by_foreign` (`created_by`),
  KEY `widgets_updated_by_foreign` (`updated_by`),
  CONSTRAINT `widgets_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `church` (`id`),
  CONSTRAINT `widgets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `widgets_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2024_01_01_000001_create_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2024_01_01_000002_create_states_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2024_01_01_000003_create_cities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_01_01_000004_create_church_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_01_01_000005_create_user_group_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_01_01_000006_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_01_01_000007_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2024_01_01_000008_create_userprofiles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2024_01_01_000009_create_oauth_auth_codes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2024_01_01_000010_create_oauth_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2024_01_01_000011_create_oauth_refresh_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2024_01_01_000012_create_oauth_clients_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2024_01_01_000013_create_oauth_personal_access_clients_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2024_01_01_000014_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2024_01_01_000015_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2024_01_01_000016_create_authentications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2024_01_01_000017_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2024_01_01_000018_create_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2024_01_01_000019_create_role_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2024_01_01_000020_create_permission_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2024_01_01_000021_create_permission_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2024_01_01_000022_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2024_01_01_000023_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2024_01_01_000024_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2024_01_01_000025_create_church_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2024_01_01_000026_create_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2024_01_01_000027_create_event_galleries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2024_01_01_000028_create_event_managers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_01_01_000029_create_event_attendance_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2024_01_01_000030_create_event_attendees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_01_01_000031_create_galleries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2024_01_01_000032_create_photos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_01_01_000033_create_media_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_01_01_000034_create_bulletins_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2024_01_01_000035_create_sermons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_01_01_000036_create_sermons_links_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2024_01_01_000037_create_page_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2024_01_01_000038_create_pages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2024_01_01_000039_create_page_attachments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2024_01_01_000040_create_page_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2024_01_01_000041_create_page_versions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2024_01_01_000042_create_post_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2024_01_01_000043_create_posts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2024_01_01_000044_create_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2024_01_01_000045_create_post_tag_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2024_01_01_000046_create_post_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2024_01_01_000047_create_post_comments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2024_01_01_000048_create_post_comment_details_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2024_01_01_000049_create_group_category_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2024_01_01_000050_create_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2024_01_01_000051_create_group_links_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2024_01_01_000052_create_notes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2024_01_01_000053_create_contacts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2024_01_01_000054_create_favorites_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2024_01_01_000055_create_votes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2024_01_01_000056_create_quotes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2024_01_01_000057_create_keywords_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2024_01_01_000058_create_helps_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2024_01_01_000059_create_attendances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2024_01_01_000060_create_feedbacks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2024_01_01_000061_create_feedback_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2024_01_01_000062_create_prayer_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2024_01_01_000063_create_prayers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2024_01_01_000064_create_prayer_participants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2024_01_01_000065_create_mailtemplates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2024_01_01_000066_create_reminder_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2024_01_01_000067_create_sms_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2024_01_01_000068_create_send_mail_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2024_01_01_000069_create_mailinglists_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2024_01_01_000070_create_mailing_list_subscribers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2024_01_01_000071_create_campaign_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2024_01_01_000072_create_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2024_01_01_000073_create_campaign_email_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2024_01_01_000074_create_subscribers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2024_01_01_000075_create_smtp_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2024_01_01_000076_create_mail_queues_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2024_01_01_000077_create_get_response_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2024_01_01_000078_create_webhooks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2024_01_01_000079_create_paymentgateways_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2024_01_01_000080_create_payaccounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2024_01_01_000081_create_funds_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2024_01_01_000082_create_faq_categories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2024_01_01_000083_create_faqs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2024_01_01_000084_create_news_letters_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2024_01_01_000085_create_bible_books_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2024_01_01_000086_create_bible_verses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2024_01_01_000087_create_widgets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2024_01_01_000088_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2024_01_01_000089_create_action_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2024_01_01_000090_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_05_22_201909_create_group_posts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_06_09_000001_create_donations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_06_09_000002_add_online_payment_gateways',1);
