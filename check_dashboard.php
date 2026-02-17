<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ReportService.php';

try {
    $rs = new ReportService();
    echo "Calling getDashboardMetrics()...\n";
    $metrics = $rs->getDashboardMetrics();
    echo "Result:\n";
    print_r($metrics);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
}
