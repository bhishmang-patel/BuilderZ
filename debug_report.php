<?php
require_once __DIR__ . '/includes/ReportService.php';
require_once __DIR__ . '/includes/functions.php';

echo "<h1>Debug Vendor Outstanding Report</h1>";

$reportService = new ReportService();
$data = $reportService->getVendorOutstanding();
$vendors = $data['vendors'];
$totals = $data['totals'];

echo "<h2>Totals</h2>";
echo "<pre>";
print_r($totals);
echo "</pre>";

echo "<h2>Vendor List</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Vendor</th><th>Total Bills</th><th>Total</th><th>Paid</th><th>Pending</th></tr>";
foreach ($vendors as $v) {
    echo "<tr>";
    echo "<td>" . $v['vendor_name'] . "</td>";
    echo "<td>" . $v['total_bills'] . "</td>";
    echo "<td>" . $v['total_amount'] . "</td>";
    echo "<td>" . $v['paid_amount'] . "</td>";
    echo "<td>" . $v['pending_amount'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
