<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = Database::getInstance();

echo "<h2>1. Last 5 Challans & Linked Vendor Data</h2>";
$sql = "SELECT c.id, c.challan_no, c.party_id, p.name as vendor_name, p.gst_number 
        FROM challans c 
        JOIN parties p ON c.party_id = p.id 
        ORDER BY c.id DESC LIMIT 5";
$challans = $db->query($sql)->fetchAll();

echo "<table border='1' cellpadding='5'><tr><th>Challan ID</th><th>Challan No</th><th>Party ID</th><th>Vendor Name</th><th>Vendor GST (in DB)</th></tr>";
foreach ($challans as $c) {
    echo "<tr>
            <td>{$c['id']}</td>
            <td>{$c['challan_no']}</td>
            <td>{$c['party_id']}</td>
            <td>{$c['vendor_name']}</td>
            <td>[" . ($c['gst_number'] ? $c['gst_number'] : 'NULL/Empty') . "]</td>
          </tr>";
}
echo "</table>";

echo "<h2>2. All Vendors (Check for Duplicates)</h2>";
$vendors = $db->query("SELECT id, name, gst_number, gst_status FROM parties WHERE party_type='vendor'")->fetchAll();

echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Name</th><th>GST Number</th><th>Status</th></tr>";
foreach ($vendors as $v) {
    $highlight = empty($v['gst_number']) ? 'style="color:red"' : 'style="color:green"';
    echo "<tr>
            <td>{$v['id']}</td>
            <td>{$v['name']}</td>
            <td $highlight>[" . ($v['gst_number'] ? $v['gst_number'] : 'Empty') . "]</td>
            <td>{$v['gst_status']}</td>
          </tr>";
}
echo "</table>";
?>
