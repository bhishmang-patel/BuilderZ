-- Add new columns for vendor management to parties table
ALTER TABLE parties
ADD COLUMN vendor_type ENUM('supplier', 'contractor', 'service_provider') NULL AFTER party_type,
ADD COLUMN city VARCHAR(100) NULL AFTER address,
ADD COLUMN gst_status ENUM('registered', 'unregistered', 'composition') DEFAULT 'unregistered' AFTER gst_number,
ADD COLUMN opening_balance DECIMAL(12,2) DEFAULT 0.00 AFTER gst_status,
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER opening_balance;
