
ALTER TABLE `payments` 
ADD COLUMN `company_account_id` INT DEFAULT NULL AFTER `party_id`,
ADD INDEX `idx_company_account_id` (`company_account_id`);

-- Optional: Add foreign key constraint if strict integrity is desired
-- ALTER TABLE `payments` ADD CONSTRAINT `fk_payments_account` FOREIGN KEY (`company_account_id`) REFERENCES `company_accounts`(`id`) ON DELETE SET NULL;
