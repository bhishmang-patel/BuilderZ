<?php
// trigger_demand.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Security token expired. Please try again.');
    $project_id = $_POST['project_id'] ?? 0;
    redirect('modules/projects/milestones.php?project_id=' . $project_id);
}

$project_id = $_POST['project_id'] ?? null;
$stage_name = $_POST['stage_name'] ?? null;

if (!$project_id || !$stage_name) {
    setFlashMessage('error', 'Missing project or stage information.');
    redirect('modules/projects/milestones.php?project_id=' . $project_id);
}

$db = Database::getInstance();

// 1. Find all eligible bookings
// - Must belong to this project
// - Must have a payment plan (stage_of_work_id IS NOT NULL)
// - Must have this specific stage_name in their plan
// - Must NOT already have a demand generated for this stage (prevent duplicates)

$sql = "SELECT b.id as booking_id, b.customer_id, b.agreement_value, swi.percentage, swi.stage_name, b.flat_id
        FROM bookings b
        JOIN stage_of_work sw ON b.stage_of_work_id = sw.id
        JOIN stage_of_work_items swi ON sw.id = swi.stage_of_work_id
        WHERE b.project_id = ? 
        AND b.status = 'active'
        AND swi.stage_name = ?
        AND NOT EXISTS (
            SELECT 1 FROM booking_demands bd 
            WHERE bd.booking_id = b.id 
            AND bd.stage_name = swi.stage_name
        )";

$eligible_bookings = $db->query($sql, [$project_id, $stage_name])->fetchAll();
$generated_count = 0;

$db->beginTransaction();
try {
    // 1. Mark Stage as Completed for the Project (Independent of bookings)
    // Use INSERT IGNORE to prevent errors if already exists
    $mark_sql = "INSERT INTO project_completed_stages (project_id, stage_name, notes) 
                 SELECT ?, ?, 'Manually triggered via Project Progress' 
                 WHERE NOT EXISTS (SELECT 1 FROM project_completed_stages WHERE project_id = ? AND stage_name = ?)";
    $db->query($mark_sql, [$project_id, $stage_name, $project_id, $stage_name]);

    // 2. Generate Demands for Eligible Bookings
    foreach ($eligible_bookings as $booking) {
        $amount = ($booking['agreement_value'] * $booking['percentage']) / 100;
        
        $demand_data = [
            'booking_id' => $booking['booking_id'],
            'stage_name' => $args['stage_name'] ?? $booking['stage_name'],
            'demand_amount' => $amount,
            'paid_amount' => 0.00,
            'status' => 'pending',
            'due_date' => date('Y-m-d', strtotime('+15 days')), // Default 15 days due
            'generated_date' => date('Y-m-d H:i:s'),
            'notes' => 'Auto-generated via Project Progress'
        ];
        
        $db->insert('booking_demands', $demand_data);
        
        // Optional: Log Audit Trail (skipped for brevity, but recommended)
        $generated_count++;
    }
    
    $db->commit();
    setFlashMessage('success', "Successfully generated $generated_count payment demands for '$stage_name'.");
    
} catch (Exception $e) {
    $db->rollback();
    setFlashMessage('error', 'Error generating demands: ' . $e->getMessage());
}

redirect('modules/projects/milestones.php?project_id=' . $project_id);
