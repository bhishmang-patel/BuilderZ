<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$cols = $db->query("DESCRIBE flats")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
