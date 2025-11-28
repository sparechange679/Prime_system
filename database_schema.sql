-- Prime Cargo Limited Database Schema
-- Automated Cargo Clearance System

-- Drop existing tables if they exist
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `shipment_documents`;
DROP TABLE IF EXISTS `verification`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `shipments`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `roles`;

-- Roles table
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `permissions` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20),
  `password` varchar(255) NOT NULL,
  `role` enum('admin','agent','client','keeper') NOT NULL DEFAULT 'client',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `tpin_number` varchar(50) NULL,
  `tpin_expiry` date NULL,
  `login_attempts` int(11) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_login_attempt` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `tpin_number` (`tpin_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clients table
CREATE TABLE IF NOT EXISTS `clients` (
  `client_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `contact_person` varchar(100),
  `address` text,
  `city` varchar(50),
  `country` varchar(50) DEFAULT 'Malawi',
  `business_license` varchar(50),
  `tax_registration` varchar(50),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipments table
CREATE TABLE IF NOT EXISTS `shipments` (
  `shipment_id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `agent_id` int(11) NULL,
  `keeper_id` int(11) NULL,
  `goods_description` text NOT NULL,
  `declared_value` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `weight` decimal(10,2) NULL,
  `origin_country` varchar(50) NOT NULL,
  `destination_port` varchar(50) NOT NULL,
  `arrival_date` date NULL,
  `status` enum('pending','under_verification','under_clearance','clearance_approved','clearance_rejected','manifest_issued','release_issued','completed','cancelled') NOT NULL DEFAULT 'pending',
  `manifest_number` varchar(50) NULL,
  `release_number` varchar(50) NULL,
  `release_date` timestamp NULL DEFAULT NULL,
  `tariff_number` varchar(20) NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount_mwk` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','completed') DEFAULT 'pending',
  `admin_notes` text NULL,
  `next_action` varchar(100) NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`shipment_id`),
  UNIQUE KEY `tracking_number` (`tracking_number`),
  UNIQUE KEY `manifest_number` (`manifest_number`),
  UNIQUE KEY `release_number` (`release_number`),
  KEY `client_id` (`client_id`),
  KEY `agent_id` (`agent_id`),
  KEY `keeper_id` (`keeper_id`),
  KEY `status` (`status`),
  CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `shipments_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_ibfk_3` FOREIGN KEY (`keeper_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipment documents table
CREATE TABLE IF NOT EXISTS `shipment_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `description` text,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_notes` text NULL,
  PRIMARY KEY (`document_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `shipment_documents_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_documents_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification table
CREATE TABLE IF NOT EXISTS `verification` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `keeper_id` int(11) NOT NULL,
  `verification_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `goods_verified` tinyint(1) DEFAULT 0,
  `documents_verified` tinyint(1) DEFAULT 0,
  `verification_notes` text,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`verification_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `keeper_id` (`keeper_id`),
  CONSTRAINT `verification_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE,
  CONSTRAINT `verification_ibfk_2` FOREIGN KEY (`keeper_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `amount_mwk` decimal(15,2) NOT NULL,
  `payment_method` varchar(50),
  `transaction_id` varchar(100),
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `shipment_id` (`shipment_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manifests table for agent manifest assignments
CREATE TABLE IF NOT EXISTS `manifests` (
  `manifest_id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `manifest_number` varchar(50) NOT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `notes` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`manifest_id`),
  UNIQUE KEY `manifest_number` (`manifest_number`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `manifests_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TPIN assignments table for agent TPIN numbers
CREATE TABLE IF NOT EXISTS `tpin_assignments` (
  `tpin_id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NOT NULL,
  `tpin_number` varchar(50) NOT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `notes` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tpin_id`),
  UNIQUE KEY `tpin_number` (`tpin_number`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `tpin_assignments_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table for client-agent communication
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `message_type` enum('update','document_request','clearance_update','payment_reminder','general') DEFAULT 'general',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `shipment_id` (`shipment_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `related_table` varchar(50) NULL,
  `related_id` int(11) NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table
CREATE TABLE IF NOT EXISTS `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NULL,
  `details` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `table_name` (`table_name`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `roles` (`role_name`, `description`) VALUES
('admin', 'System Administrator with full access'),
('agent', 'Cargo Agent with clearance permissions'),
('client', 'Client with shipment management access'),
('keeper', 'Warehouse Keeper with verification permissions');

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `role`, `status`) VALUES
('System Administrator', 'admin@primecargo.mw', '+265123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Insert sample agent user (password: agent123)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `role`, `status`) VALUES
('John Agent', 'agent@primecargo.mw', '+265123456790', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active');

-- Insert sample keeper user (password: keeper123)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `role`, `status`) VALUES
('Mary Keeper', 'keeper@primecargo.mw', '+265123456791', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'keeper', 'active');

-- Insert sample client user (password: client123)
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `role`, `status`) VALUES
('ABC Company', 'client@abc.com', '+265123456792', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'active');

-- Insert sample client details
INSERT INTO `clients` (`user_id`, `company_name`, `contact_person`, `address`, `city`, `country`) VALUES
(4, 'ABC Company Limited', 'John Doe', '123 Business Street', 'Blantyre', 'Malawi');

-- Insert sample shipment
INSERT INTO `shipments` (`tracking_number`, `client_id`, `agent_id`, `goods_description`, `declared_value`, `origin_country`, `destination_port`, `status`) VALUES
('TRK001', 1, 2, 'Electronics and Machinery Parts', 5000.00, 'China', 'Chileka Airport', 'pending');

-- Sample messages data
INSERT INTO `messages` (`sender_id`, `recipient_id`, `shipment_id`, `subject`, `content`, `message_type`, `created_at`) VALUES
(2, 4, 1, 'Shipment Status Update', 'Dear Client, your shipment TRK001 status has been updated to "Under Verification". We will keep you informed of any further developments.', 'update', '2025-01-07 10:00:00'),
(2, 4, 1, 'Document Request', 'Please provide additional documents for shipment TRK001 to proceed with clearance. Required: Commercial Invoice, Packing List.', 'document_request', '2025-01-07 11:00:00');
