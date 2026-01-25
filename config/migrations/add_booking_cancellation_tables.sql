-- Booking Cancellations Table
CREATE TABLE IF NOT EXISTS booking_cancellations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial Transactions Table (for income/expenditure tracking)
CREATE TABLE IF NOT EXISTS financial_transactions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update payments table to support refunds
ALTER TABLE payments 
MODIFY COLUMN payment_type ENUM('customer_receipt', 'vendor_payment', 'labour_payment', 'customer_refund') NOT NULL;

ALTER TABLE payments 
MODIFY COLUMN reference_type ENUM('booking', 'challan', 'booking_cancellation') NOT NULL;
