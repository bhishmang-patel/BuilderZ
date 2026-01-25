<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

$db = Database::getInstance();

try {
    echo "Running booking cancellation migration...\n\n";
    
    // Create booking_cancellations table
    echo "Creating booking_cancellations table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS booking_cancellations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        cancellation_date DATE NOT NULL,
        total_paid DECIMAL(12,2) NOT NULL,
        refund_amount DECIMAL(12,2) NOT NULL,
        deduction_amount DECIMAL(12,2) NOT NULL,
        deduction_reason VARCHAR(255),
        refund_mode ENUM('cash', 'bank', 'upi', 'cheque') NOT NULL,
        refund_reference VARCHAR(100),
        cancellation_reason VARCHAR(255) NOT NULL,
        remarks TEXT,
        processed_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_booking (booking_id),
        INDEX idx_date (cancellation_date),
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
        FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    echo "✓ booking_cancellations table created successfully\n\n";
    
    // Create financial_transactions table
    echo "Creating financial_transactions table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS financial_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_type ENUM('income', 'expenditure') NOT NULL,
        category VARCHAR(100) NOT NULL,
        reference_type VARCHAR(50),
        reference_id INT,
        project_id INT,
        transaction_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        description TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (transaction_type),
        INDEX idx_category (category),
        INDEX idx_date (transaction_date),
        INDEX idx_project (project_id),
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    echo "✓ financial_transactions table created successfully\n\n";
    
    // Update payments table to support refunds
    echo "Updating payments table...\n";
    
    // Check if customer_refund already exists
    $result = $db->query("SHOW COLUMNS FROM payments WHERE Field = 'payment_type'")->fetch();
    if ($result && strpos($result['Type'], 'customer_refund') === false) {
        $sql = "ALTER TABLE payments 
                MODIFY COLUMN payment_type ENUM('customer_receipt', 'vendor_payment', 'labour_payment', 'customer_refund') NOT NULL";
        $db->query($sql);
        echo "✓ Added customer_refund to payment_type enum\n";
    } else {
        echo "✓ customer_refund already exists in payment_type\n";
    }
    
    // Check if booking_cancellation already exists
    $result = $db->query("SHOW COLUMNS FROM payments WHERE Field = 'reference_type'")->fetch();
    if ($result && strpos($result['Type'], 'booking_cancellation') === false) {
        $sql = "ALTER TABLE payments 
                MODIFY COLUMN reference_type ENUM('booking', 'challan', 'booking_cancellation') NOT NULL";
        $db->query($sql);
        echo "✓ Added booking_cancellation to reference_type enum\n";
    } else {
        echo "✓ booking_cancellation already exists in reference_type\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nNew features added:\n";
    echo "- Booking cancellation management\n";
    echo "- Refund tracking\n";
    echo "- Deduction/cancellation charges\n";
    echo "- Financial transactions tracking\n";
    echo "- Income and expenditure management\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
