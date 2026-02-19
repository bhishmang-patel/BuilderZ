-- Phase 1: Tax and Configuration Updates

-- 1. Materials: Add tax_rate
ALTER TABLE materials ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 0;

-- 2. Purchase Order Items: Add tax info
ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 0;
ALTER TABLE purchase_order_items ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(15,2) DEFAULT 0;

-- 3. Bills: Add taxable_amount and tax_amount
ALTER TABLE bills ADD COLUMN IF NOT EXISTS taxable_amount DECIMAL(15,2) DEFAULT 0;
ALTER TABLE bills ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(15,2) DEFAULT 0;

-- 4. Challans: Add tax info and grand_total
ALTER TABLE challans ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(15,2) DEFAULT 0;
ALTER TABLE challans ADD COLUMN IF NOT EXISTS grand_total DECIMAL(15,2) DEFAULT 0;

-- 5. Challan Items: Add tax info and allow nullable rate/amount (or default 0)
ALTER TABLE challan_items MODIFY COLUMN rate DECIMAL(10,2) DEFAULT 0;
ALTER TABLE challan_items MODIFY COLUMN total_amount DECIMAL(15,2) DEFAULT 0;
ALTER TABLE challan_items ADD COLUMN IF NOT EXISTS tax_rate DECIMAL(5,2) DEFAULT 0;
ALTER TABLE challan_items ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(15,2) DEFAULT 0;

-- 6. Settings Table for T&C and Prefixes
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings if they don't exist
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('company_name', 'BuilderZ Construction'),
('company_address', '123 Builder Lane, City'),
('company_gst', ''),
('challan_prefix', 'MAT'),
('po_prefix', 'PO'),
('booking_prefix', 'BK'),
('receipt_terms', '1. Cheques are subject to realization.\n2. This receipt is valid only for the amount specified.'),
('po_terms', '1. Delivery must be made within the specified date.\n2. Goods must match the specifications.');
