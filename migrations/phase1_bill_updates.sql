-- Phase 1 Bill Updates
-- Add bill_id to challans to support multiple challans per bill
ALTER TABLE challans ADD COLUMN bill_id INT NULL;
ALTER TABLE challans ADD CONSTRAINT fk_challan_bill FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE SET NULL;

-- Add Tax columns to bills table
ALTER TABLE bills ADD COLUMN taxable_amount DECIMAL(12,2) DEFAULT 0 AFTER amount;
ALTER TABLE bills ADD COLUMN tax_amount DECIMAL(12,2) DEFAULT 0 AFTER taxable_amount;
ALTER TABLE bills MODIFY amount DECIMAL(12,2) NOT NULL COMMENT 'Grand Total';
