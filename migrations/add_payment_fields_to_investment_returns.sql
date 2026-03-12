-- Add payment_mode and company_account_id columns to investment_returns table
ALTER TABLE investment_returns
    ADD COLUMN payment_mode VARCHAR(50) DEFAULT NULL AFTER remarks,
    ADD COLUMN company_account_id INT DEFAULT NULL AFTER payment_mode;
