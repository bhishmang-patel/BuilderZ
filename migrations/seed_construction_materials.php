<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

$materials = [
    // Steel/Iron
    ['material_name' => 'TMT Bars', 'unit' => 'kg'],
    ['material_name' => 'Binding Wire', 'unit' => 'kg'],
    
    // Aggregates
    ['material_name' => 'Aggregate', 'unit' => 'brass'],
    ['material_name' => 'Sand', 'unit' => 'brass'],
    ['material_name' => 'Dust/Crush Sand', 'unit' => 'brass'],
    
    // Cement
    ['material_name' => 'PPC Cement', 'unit' => 'bag'],
    ['material_name' => 'OPC Cement', 'unit' => 'bag'],
    ['material_name' => 'White Cement', 'unit' => 'bag'],
    
    // Blocks/Bricks
    ['material_name' => 'Red Bricks', 'unit' => 'nos'],
    ['material_name' => 'AAC Blocks', 'unit' => 'nos'],
    ['material_name' => 'Fly Ash Bricks', 'unit' => 'nos'],
    
    // Wood/Board
    ['material_name' => 'Plywood', 'unit' => 'sqft'],
    ['material_name' => 'MDF Board', 'unit' => 'sqft'],
    ['material_name' => 'Timber/Teak', 'unit' => 'cft'],
    
    // Tiles/Stone
    ['material_name' => 'Vitrified Tiles', 'unit' => 'sqft'],
    ['material_name' => 'Ceramic Tiles', 'unit' => 'sqft'],
    ['material_name' => 'Granite', 'unit' => 'sqft'],
    ['material_name' => 'Marble', 'unit' => 'sqft'],
    
    // Plumbing
    ['material_name' => 'PVC Pipe', 'unit' => 'nos'],
    ['material_name' => 'CPVC Pipe', 'unit' => 'nos'],
    ['material_name' => 'Water Tank', 'unit' => 'nos'],
    
    // Electrical
    ['material_name' => 'Copper Wire', 'unit' => 'nos'],
    ['material_name' => 'Switches', 'unit' => 'nos'],
    ['material_name' => 'PVC Conduit', 'unit' => 'nos'],
    
    // Chemicals/Paints
    ['material_name' => 'Waterproofing Compound', 'unit' => 'ltr'],
    ['material_name' => 'Primer', 'unit' => 'ltr'],
    ['material_name' => 'Emulsion Paint', 'unit' => 'ltr'],
    ['material_name' => 'Putty', 'unit' => 'kg'],
    
    // Others
    ['material_name' => 'Nails', 'unit' => 'kg'],
    ['material_name' => 'Screws', 'unit' => 'nos'],
    ['material_name' => 'Shuttering Oil', 'unit' => 'ltr'],
    ['material_name' => 'Curing Compound', 'unit' => 'ltr']
];

echo "Starting material seed...\n";
$addedCount = 0;

foreach ($materials as $m) {
    // Check if material already exists (case-insensitive)
    $stmt = $db->query("SELECT id FROM materials WHERE LOWER(material_name) = LOWER(?)");
    $stmt->execute([$m['material_name']]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        $db->insert('materials', [
            'material_name' => $m['material_name'],
            'unit' => $m['unit'],
            'default_rate' => 0,
            'current_stock' => 0
        ]);
        echo "Added: {$m['material_name']} ({$m['unit']})\n";
        $addedCount++;
    } else {
        echo "Already exists: {$m['material_name']}\n";
    }
}

echo "Seeding complete. Added $addedCount new materials.\n";
