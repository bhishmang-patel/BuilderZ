<?php
require_once __DIR__ . '/includes/MasterService.php';

try {
    $service = new MasterService();
    $projects = $service->getAllProjects(['search' => 'Sun Height']);
    
    foreach ($projects as $p) {
        echo "Project: " . $p['project_name'] . "\n";
        echo "ID: " . $p['id'] . "\n";
        echo "Has Multiple Towers: " . $p['has_multiple_towers'] . "\n";
        echo "Tower Count: " . $p['tower_count'] . "\n";
        echo "-------------------\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
