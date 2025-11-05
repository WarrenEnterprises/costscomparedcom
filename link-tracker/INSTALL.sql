-- ═══════════════════════════════════════════════════════════════
-- Link Tracker - Complete Installation SQL
-- Version: 1.0.0
-- Description: Creates all database tables, indexes, and default admin user
-- Instructions: Run this entire script in PHPMyAdmin
-- Safe to run multiple times (uses IF NOT EXISTS)
-- ═══════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ═══════════════════════════════════════════════════════════════
-- Table 1: lt_links
-- Stores all tracked redirect links and their configurations
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
-- Table 2: lt_clicks
-- Stores all click tracking data with enhanced analytics
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `referrer` text DEFAULT NULL,
  `inferred_source` varchar(255) DEFAULT NULL,
  `source_confidence` enum('high','medium','low','none') DEFAULT 'none',
  `query_params` text DEFAULT NULL,
  `request_uri` varchar(500) DEFAULT NULL,
  `accept_language` varchar(50) DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `browser_version` varchar(20) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `os_version` varchar(50) DEFAULT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `is_unique` tinyint(1) NOT NULL DEFAULT 0,
  `is_bot` tinyint(1) NOT NULL DEFAULT 0,
  `clicked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  KEY `fingerprint` (`fingerprint`),
  KEY `clicked_at` (`clicked_at`),
  KEY `is_unique` (`is_unique`),
  KEY `is_bot` (`is_bot`),
  KEY `country_code` (`country_code`),
  KEY `device_type` (`device_type`),
  KEY `inferred_source` (`inferred_source`),
  KEY `link_clicked` (`link_id`, `clicked_at`),
  CONSTRAINT `lt_clicks_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `lt_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table 3: lt_placements
-- Tracks where redirect links are placed across the web
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_placements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `url` text NOT NULL,
  `star_rating` tinyint(1) NOT NULL DEFAULT 3,
  `platform` varchar(50) DEFAULT NULL,
  `platform_custom` varchar(100) DEFAULT NULL,
  `status` enum('active','removed','broken') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  KEY `status` (`status`),
  KEY `star_rating` (`star_rating`),
  KEY `platform` (`platform`),
  KEY `link_status` (`link_id`, `status`),
  CONSTRAINT `lt_placements_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `lt_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Table 4: lt_users
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
-- Table 5: lt_sessions
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
-- Table 6: lt_login_attempts
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
-- Table 7: lt_system_resets
-- Logs system data resets
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `lt_system_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reset_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `clicks_before_reset` int(11) NOT NULL DEFAULT 0,
  `links_before_reset` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- Default Admin User
-- Username: admin
-- Password: changeme123
-- ⚠️ CHANGE PASSWORD IMMEDIATELY AFTER FIRST LOGIN!
-- ═══════════════════════════════════════════════════════════════

INSERT INTO `lt_users` (`username`, `password_hash`) 
VALUES ('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GkJRvVVVpBVG')
ON DUPLICATE KEY UPDATE username=username;

-- Password hash for: changeme123
-- Generated with: password_hash('changeme123', PASSWORD_BCRYPT, ['cost' => 12])

-- ═══════════════════════════════════════════════════════════════
-- Installation Complete!
-- ═══════════════════════════════════════════════════════════════

-- Verify tables were created
SHOW TABLES LIKE 'lt_%';

-- Count should show 7 tables:
-- lt_links, lt_clicks, lt_placements, lt_users, lt_sessions, 
-- lt_login_attempts, lt_system_resets

