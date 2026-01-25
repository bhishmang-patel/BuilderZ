<?php
require_once __DIR__ . '/config/database.php';

echo "Starting Verification...\n";

try {
    $db = Database::getInstance();
    
    // 1. Get a project ID (any existing one)
    $stmt = $db->query("SELECT id FROM projects LIMIT 1");
    $project = $stmt->fetch();
    if (!$project) {
        die("No projects found to test with. Create a project first.\n");
    }
    $projectId = $project['id'];
    echo "Using Project ID: $projectId\n";

    // 2. Create
    echo "Testing INSERT...\n";
    $date = date('Y-m-d');
    $amount = 50000.00;
    $source = 'Test Source';
    
    $id = $db->insert('project_investments', [
        'project_id' => $projectId,
        'investment_date' => $date,
        'amount' => $amount,
        'source' => $source,
        'remarks' => 'Test Remark'
    ]);
    echo "Inserted Investment ID: $id\n";

    // 3. Read
    echo "Testing READ...\n";
    $stmt = $db->select('project_investments', 'id = ?', [$id]);
    $inv = $stmt->fetch();
    if ($inv && $inv['amount'] == $amount) {
        echo "Read Success: Amount matches.\n";
    } else {
        echo "Read Failed!\n";
    }

    // 4. Update
    echo "Testing UPDATE...\n";
    $newAmount = 75000.00;
    $db->update('project_investments', ['amount' => $newAmount], 'id = ?', ['id' => $id]);
    
    $stmt = $db->select('project_investments', 'id = ?', [$id]);
    $inv = $stmt->fetch();
    if ($inv && $inv['amount'] == $newAmount) {
        echo "Update Success: Amount updated.\n";
    } else {
        echo "Update Failed!\n";
    }

    // 5. Delete
    echo "Testing DELETE...\n";
    $db->delete('project_investments', 'id = ?', [$id]);
    
    $stmt = $db->select('project_investments', 'id = ?', [$id]);
    if (!$stmt->fetch()) {
        echo "Delete Success: Record gone.\n";
    } else {
        echo "Delete Failed!\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
