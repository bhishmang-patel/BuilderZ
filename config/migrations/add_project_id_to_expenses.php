<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

$db = Database::getInstance();

echo "Checking expenses table schema...\n";

try {
    // Check if column exists
    $check = $db->query("SHOW COLUMNS FROM expenses LIKE 'project_id'");
    if ($check->rowCount() > 0) {
        echo "Column 'project_id' already exists. Skipping.\n";
    } else {
        echo "Adding 'project_id' column...\n";
        $db->query("ALTER TABLE expenses ADD COLUMN project_id INT NULL AFTER id");
        $db->query("ALTER TABLE expenses ADD CONSTRAINT fk_expenses_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL");
        echo "Column added successfully!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Database update completed.\n";
