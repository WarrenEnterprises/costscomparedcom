-- ═══════════════════════════════════════════════════════════════
-- Link Tracker Database Schema
-- Version: 1.0.0
-- ═══════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ═══════════════════════════════════════════════════════════════
-- Table: lt_links
-- Stores all tracked links and their configurations
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `destination_url` text NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `cloaking_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cloaking_url` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `is_active` (`is_active`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table: lt_clicks
-- Stores all click tracking data
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `referrer` text DEFAULT NULL,
  `query_params` text DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `is_unique` tinyint(1) NOT NULL DEFAULT 0,
  `clicked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  KEY `fingerprint` (`fingerprint`),
  KEY `clicked_at` (`clicked_at`),
  KEY `is_unique` (`is_unique`),
  KEY `country_code` (`country_code`),
  KEY `device_type` (`device_type`),
  KEY `link_clicked` (`link_id`, `clicked_at`),
  CONSTRAINT `lt_clicks_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `lt_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table: lt_users
-- Admin users for the system
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table: lt_login_attempts
-- Tracks failed login attempts for brute force protection
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ip_attempted` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table: lt_sessions
-- Manages admin user sessions
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `lt_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `lt_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table: lt_system_resets
-- Logs system data resets
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_system_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reset_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `clicks_before_reset` int(11) NOT NULL DEFAULT 0,
  `links_before_reset` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

