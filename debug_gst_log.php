<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();
$output = "";

$output .= "=== LAST 5 CHALLANS ===\n";
$challans = $db->query("SELECT c.id, c.challan_no, c.party_id, p.name, p.gst_number, p.gst_status FROM challans c JOIN parties p ON c.party_id = p.id ORDER BY c.id DESC LIMIT 5")->fetchAll();
foreach ($challans as $c) {
    $output .= "Challan: {$c['challan_no']} | PartyID: {$c['party_id']} | Name: {$c['name']} | GST: [{$c['gst_number']}] | Status: {$c['gst_status']}\n";
}

$output .= "\n=== ALL VENDORS ===\n";
$vendors = $db->query("SELECT id, name, gst_number, gst_status FROM parties WHERE party_type='vendor'")->fetchAll();
foreach ($vendors as $v) {
    $output .= "ID: {$v['id']} | Name: {$v['name']} | GST: [{$v['gst_number']}] | Status: {$v['gst_status']}\n";
}

file_put_contents('debug_output.txt', $output);
echo "Done";
?>
