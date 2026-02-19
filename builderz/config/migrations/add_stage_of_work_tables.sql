-- Add stage_of_work table (Templates)
CREATE TABLE IF NOT EXISTS `stage_of_work` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `total_stages` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add stage_of_work_items table (The breakdown steps)
CREATE TABLE IF NOT EXISTS `stage_of_work_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_of_work_id` int(11) NOT NULL,
  `stage_name` varchar(100) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `stage_order` int(11) NOT NULL,
  `stage_type` enum('booking','time_linked','construction_linked') DEFAULT 'construction_linked',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`stage_of_work_id`) REFERENCES `stage_of_work`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add booking_demands table (Generated Letters)
CREATE TABLE IF NOT EXISTS `booking_demands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `stage_name` varchar(100) NOT NULL,
  `demand_amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid') DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `generated_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link bookings to a plan
ALTER TABLE `bookings` ADD COLUMN `stage_of_work_id` int(11) DEFAULT NULL AFTER `project_id`;
ALTER TABLE `bookings` ADD CONSTRAINT `fk_booking_stage_work` FOREIGN KEY (`stage_of_work_id`) REFERENCES `stage_of_work`(`id`) ON DELETE SET NULL;
