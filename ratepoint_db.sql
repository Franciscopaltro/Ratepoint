-- Ratepoint Database Export
-- Created for XAMPP MySQL Import

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Database: `ratepoint_db`
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `ratepoint_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ratepoint_db`;

-- --------------------------------------------------------
-- Table structure for table `zones`
-- --------------------------------------------------------
CREATE TABLE `zones` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `boundary_coords` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('super_admin','finance_officer','supervisor','field_agent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'field_agent',
  `zone_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_zone_id_foreign` (`zone_id`),
  CONSTRAINT `users_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `businesses`
-- --------------------------------------------------------
CREATE TABLE `businesses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,8) NOT NULL,
  `gps_lng` decimal(11,8) NOT NULL,
  `zone_id` bigint(20) UNSIGNED NOT NULL,
  `structure_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `levy_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fee_amount` decimal(10,2) NOT NULL,
  `status` enum('paid','unpaid','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `businesses_zone_id_foreign` (`zone_id`),
  CONSTRAINT `businesses_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `collections`
-- --------------------------------------------------------
CREATE TABLE `collections` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `agent_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `receipt_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,8) NOT NULL,
  `gps_lng` decimal(11,8) NOT NULL,
  `offline_sync_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collected_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `collections_receipt_number_unique` (`receipt_number`),
  KEY `collections_business_id_foreign` (`business_id`),
  KEY `collections_agent_id_foreign` (`agent_id`),
  CONSTRAINT `collections_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `collections_business_id_foreign` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `reconciliations`
-- --------------------------------------------------------
CREATE TABLE `reconciliations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `collection_id` bigint(20) UNSIGNED NOT NULL,
  `finance_officer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','verified','suspicious') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `confirmed_amount` decimal(10,2) DEFAULT NULL,
  `bank_slip_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_deposit_date` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reconciliations_collection_id_foreign` (`collection_id`),
  KEY `reconciliations_finance_officer_id_foreign` (`finance_officer_id`),
  CONSTRAINT `reconciliations_collection_id_foreign` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reconciliations_finance_officer_id_foreign` FOREIGN KEY (`finance_officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `suspicious_activities`
-- --------------------------------------------------------
CREATE TABLE `suspicious_activities` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','investigating','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Initial Data Seeding
-- --------------------------------------------------------

INSERT INTO `zones` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Zone A - Central Business District', NOW(), NOW()),
(2, 'Zone B - Industrial Area', NOW(), NOW());

-- Passwords are all 'password'
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `zone_id`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@ratepoint.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NULL, NOW(), NOW()),
(2, 'John Finance', 'finance@ratepoint.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'finance_officer', NULL, NOW(), NOW()),
(3, 'Kwame Agent', 'agent@ratepoint.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'field_agent', 1, NOW(), NOW());

INSERT INTO `businesses` (`id`, `name`, `owner_name`, `gps_lat`, `gps_lng`, `zone_id`, `structure_type`, `levy_type`, `fee_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ama\'s Provision Shop', 'Ama Serwaa', 5.60370000, -0.18700000, 1, 'Permanent', 'Business Operating Permit', 150.00, 'unpaid', NOW(), NOW()),
(2, 'Kofi Brothers Garage', 'Kofi Mensah', 5.61000000, -0.19000000, 1, 'Temporary', 'Store Levy', 300.00, 'unpaid', NOW(), NOW());

COMMIT;
