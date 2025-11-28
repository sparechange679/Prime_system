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

-- Sample messages data
INSERT INTO `messages` (`sender_id`, `recipient_id`, `shipment_id`, `subject`, `content`, `message_type`, `created_at`) VALUES
(2, 3, 1, 'Shipment Status Update', 'Dear Client, your shipment TRK001 status has been updated to "Under Verification". We will keep you informed of any further developments.', 'update', '2025-01-07 10:00:00'),
(2, 3, 1, 'Document Request', 'Please provide additional documents for shipment TRK001 to proceed with clearance. Required: Commercial Invoice, Packing List.', 'document_request', '2025-01-07 11:00:00');
