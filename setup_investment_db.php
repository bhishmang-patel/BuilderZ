<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS project_investments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        investment_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        source VARCHAR(100) DEFAULT 'Self',
        remarks TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $conn->exec($sql);
    echo "Table 'project_investments' created successfully or already exists.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
