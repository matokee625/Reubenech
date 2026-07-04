-- phpMyAdmin SQL Dump
-- Database: `milkproject.db`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Disable foreign key checks so we can safely drop tables
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `milkproject.db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `milkproject.db`;

-- --------------------------------------------------------
-- Safely drop all existing tables in reverse dependency order
-- This completely avoids Error #1451 (Foreign Key Constraint Fails)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `access_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `advertisements`;
DROP TABLE IF EXISTS `milk_postings`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `status` enum('active','suspended','trash') NOT NULL DEFAULT 'active',
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`username`, `email`, `password`, `role`, `status`, `registered_at`, `last_login`) VALUES
('REUBENTECH', 'reubenmatoke2005@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2025-11-02 22:16:46', '2025-11-10 08:27:28'),
('lucas_muller', 'l.muller@example.de', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member', 'active', '2025-11-10 08:30:10', '2025-11-01 13:02:10'),
('sofia_ndersson', 'sofia.ndersson@example.se', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member', 'suspended', '2025-11-10 08:29:03', '2025-10-15 15:43:45'),
('Amara_akonkwo', 'amara-akonkwo@afriexample.ng', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'member', 'suspended', '2025-11-10 08:30:18', '2025-11-01 21:19:23'),
('emergency_admin', 'admin@localhost.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2025-11-02 22:11:46', '2025-11-02 22:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `milk_postings`
--

CREATE TABLE `milk_postings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `liters` decimal(10,2) NOT NULL,
  `milk_type` varchar(50) NOT NULL,
  `asking_price` decimal(10,2) NOT NULL,
  `status` enum('active','sold','cancelled') NOT NULL DEFAULT 'active',
  `posted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_posting_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advertisements`
--

CREATE TABLE `advertisements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_ad_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `posting_id` int(11) NOT NULL,
  `volume` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('completed','pending','cancelled') NOT NULL DEFAULT 'pending',
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `seller_id` (`seller_id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `posting_id` (`posting_id`),
  CONSTRAINT `fk_trans_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_post` FOREIGN KEY (`posting_id`) REFERENCES `milk_postings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
-- Stores key-value configuration pairs for admin settings
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Default settings data
--

INSERT INTO `settings` (`key`, `value`) VALUES
('site_name', 'Reubentech Hub'),
('site_email', 'reubenmatoke2005@gmail.com'),
('site_phone', '+254 799031535'),
('currency', 'KSH'),
('timezone', 'Africa/Nairobi'),
('maintenance', '0'),
('min_password_length', '8'),
('max_login_attempts', '5'),
('session_timeout_mins', '30'),
('allow_registration', '1'),
('require_2fa', '0'),
('primary_color', '#2563eb'),
('sidebar_theme', 'dark'),
('items_per_page', '20');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
-- Admin system notifications / alerts
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('info','warning','success','danger') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample notifications
--

INSERT INTO `notifications` (`type`, `title`, `message`, `link`, `is_read`) VALUES
('success', 'System Installed', 'Milk Production system was installed and configured successfully.', 'install.php', 0),
('info', 'Welcome to Reubentech Hub', 'Your admin dashboard is ready. Start by managing users and milk postings.', 'homepage.php', 0),
('warning', 'Review Suspended Accounts', 'There are suspended user accounts that need review.', 'users.php?status=suspended', 0);

-- --------------------------------------------------------

--
-- Table structure for table `access_logs`
-- Tracks admin login activity
--

CREATE TABLE `access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL DEFAULT 'login',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_registry`
--

CREATE TABLE `api_registry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(64) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documentation`
--

CREATE TABLE `documentation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Re-enable foreign key checks after all tables are created
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;