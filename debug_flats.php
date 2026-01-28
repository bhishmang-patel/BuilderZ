<?php
require_once __DIR__ . '/includes/MasterService.php';

try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT flat_no FROM flats WHERE project_id = 13 LIMIT 10");
    $stmt->execute();
    $flats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Flats for Project 13:\n";
    foreach ($flats as $f) {
        echo $f['flat_no'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
