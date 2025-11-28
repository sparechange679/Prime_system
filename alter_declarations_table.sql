-- Migration: add extra declaration columns (run once)
-- NOTE: These statements are idempotent only if you run them once. If you already have the column, rerunning will error.

ALTER TABLE `declarations` ADD COLUMN `nature_of_transaction` VARCHAR(50) NULL AFTER `country_destination`;
ALTER TABLE `declarations` ADD COLUMN `warehouse_code` VARCHAR(50) NULL AFTER `location_goods`;
ALTER TABLE `declarations` ADD COLUMN `period` VARCHAR(50) NULL AFTER `warehouse_code`;
ALTER TABLE `declarations` ADD COLUMN `previous_document_ref` VARCHAR(100) NULL AFTER `invoice_value`;
ALTER TABLE `declarations` ADD COLUMN `assessment_date` DATE NULL AFTER `assessment_number`;
ALTER TABLE `declarations` ADD COLUMN `receipt_date` DATE NULL AFTER `receipt_number`;
ALTER TABLE `declarations` ADD COLUMN `guarantee` VARCHAR(100) NULL AFTER `bank_code`;
ALTER TABLE `declarations` ADD COLUMN `validation_ref` VARCHAR(100) NULL AFTER `notes`;
