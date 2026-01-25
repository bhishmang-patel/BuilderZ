<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

try {
    $db->query("ALTER TABLE bookings ADD COLUMN registration_amount DECIMAL(12,2) DEFAULT 0.00 AFTER stamp_duty_registration");
    $db->query("ALTER TABLE bookings ADD COLUMN gst_amount DECIMAL(12,2) DEFAULT 0.00 AFTER registration_amount");
    echo "Columns added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
