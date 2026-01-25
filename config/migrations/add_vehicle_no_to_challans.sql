-- Add vehicle_no column to challans table
ALTER TABLE challans 
ADD COLUMN vehicle_no VARCHAR(50) NULL AFTER challan_date;
