
CREATE TABLE IF NOT EXISTS `company_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(100) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add account_id to payments table if not exists (for linking payments to bank accounts)
-- We will handle this in a separate step or check if it exists first.
-- For now, let's just create the master table.
