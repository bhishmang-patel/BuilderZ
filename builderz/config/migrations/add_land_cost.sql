
ALTER TABLE `projects` 
ADD COLUMN `land_cost` DECIMAL(15,2) DEFAULT 0.00 AFTER `location`;
