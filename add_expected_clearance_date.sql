-- Add missing expected_clearance_date column to shipments table
USE prime_cargo_db;

-- Add expected_clearance_date column to shipments table
ALTER TABLE `shipments` 
ADD COLUMN `expected_clearance_date` date NULL AFTER `arrival_date`;

-- Add notes column if it doesn't exist (referenced in new_shipment.php)
ALTER TABLE `shipments` 
ADD COLUMN `notes` text NULL AFTER `expected_clearance_date`;

-- Verify the changes
DESCRIBE shipments;

