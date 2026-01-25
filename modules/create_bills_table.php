<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

try {
    $sql = "CREATE TABLE IF NOT EXISTS bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_no VARCHAR(50) NOT NULL,
        bill_date DATE NOT NULL,
        party_id INT NOT NULL,
        challan_id INT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending', 'partial', 'paid') NOT NULL DEFAULT 'pending',
        file_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,
        FOREIGN KEY (party_id) REFERENCES parties(id),
        FOREIGN KEY (challan_id) REFERENCES challans(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);
    echo "Table 'bills' created successfully or already exists.";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
