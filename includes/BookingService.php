<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class BookingService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createBooking($data, $userId) {
        try {
            $this->db->beginTransaction();

            $customer_name = trim($data['customer_name']);
            $customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;

            // Create new customer if needed
            if (empty($customer_id)) {
                $existing_customer = $this->db->query("SELECT id FROM parties WHERE LOWER(name) = LOWER(?) AND party_type='customer'", [$customer_name])->fetch();
                
                if ($existing_customer) {
                    $customer_id = $existing_customer['id'];
                } else {
                    $customer_data = [
                        'party_type' => 'customer',
                        'name' => $customer_name,
                        'mobile' => sanitize($data['mobile'] ?? ''),
                        'email' => sanitize($data['email'] ?? ''),
                        'address' => sanitize($data['address'] ?? '')
                    ];
                    $customer_id = $this->db->insert('parties', $customer_data);
                }
            }

            $flat_id = intval($data['flat_id']);
            $project_id = intval($data['project_id']);
            $agreement_value = floatval($data['agreement_value']);
            $booking_date = $data['booking_date'];
            $referred_by = isset($data['referred_by']) ? sanitize($data['referred_by']) : null;
            // 1. Verify Flat Availability & Lock logic
            $flat = $this->db->query("SELECT id, status, area_sqft FROM flats WHERE id = ? FOR UPDATE", [$flat_id])->fetch();
            if (!$flat) {
                throw new Exception("Invalid flat selected.");
            }
            if ($flat['status'] !== 'available') {
                throw new Exception("This flat is already booked or sold.");
            }

            // 2. Financial Calculations (Server-Side Authority)
            // We accept Agreement Value as negotiated, but recalculate all taxes/duties
            if ($agreement_value <= 0) {
                throw new Exception("Agreement value must be greater than 0.");
            }

            // Stamp Duty (6%)
            $stamp_duty_registration = round($agreement_value * 0.06);

            // Registration (1% with Cap of 30,000)
            $registration_amount = round($agreement_value * 0.01);
            if ($agreement_value >= 3000000) {
                $registration_amount = 30000;
            }

            // GST (1%)
            $gst_amount = round($agreement_value * 0.01);

            // Derived Rate
            $rate = ($flat['area_sqft'] > 0) ? ($agreement_value / $flat['area_sqft']) : 0.00;

            // Extra Charges (still user-defined but sanitized)
            $development_charge = floatval($data['development_charge'] ?? 0);
            $parking_charge = floatval($data['parking_charge'] ?? 0);
            $society_charge = floatval($data['society_charge'] ?? 0);

            // Create booking
            $booking_data = [
                'flat_id' => $flat_id,
                'customer_id' => $customer_id,
                'project_id' => $project_id,
                'agreement_value' => $agreement_value,
                'booking_date' => $booking_date,
                'referred_by' => $referred_by,
                'rate' => $rate,
                'stamp_duty_registration' => $stamp_duty_registration,
                'registration_amount' => $registration_amount,
                'gst_amount' => $gst_amount,
                'development_charge' => $development_charge,
                'parking_charge' => $parking_charge,
                'society_charge' => $society_charge,
                'stage_of_work_id' => !empty($data['stage_of_work_id']) ? intval($data['stage_of_work_id']) : null,
                'status' => 'active',
                'created_by' => $userId,
                'total_pending' => $agreement_value,
                'total_received' => 0
            ];
            
            $booking_id = $this->db->insert('bookings', $booking_data);
            
            // Auto-Catchup: Generate demands for stages that are already completed for this project
            $this->syncProjectMilestones($booking_id, $project_id, $booking_data['stage_of_work_id'], $agreement_value);
            
            // Update flat status to booked
            $this->db->update('flats', ['status' => 'booked'], 'id = ?', ['id' => $flat_id]);
            
            logAudit('create', 'bookings', $booking_id, null, $booking_data);
            $this->db->commit();
            
            return [
                'success' => true,
                'booking_id' => $booking_id,
                'message' => 'Booking created successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function syncProjectMilestones($bookingId, $projectId, $stageOfWorkId, $agreementValue) {
        if (empty($stageOfWorkId)) return;

        // 1. Get all completed stages for this project
        // We check both the designated completion table AND legacy demands
        $sql = "SELECT stage_name FROM project_completed_stages WHERE project_id = ?
                UNION
                SELECT DISTINCT bd.stage_name 
                FROM booking_demands bd 
                JOIN bookings b ON bd.booking_id = b.id 
                WHERE b.project_id = ?";
        $stmt = $this->db->query($sql, [$projectId, $projectId]);
        $completedStages = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($completedStages)) return;

        // 2. Get the items for the current booking's plan, ORDERED BY SEQUENCE
        // This ensures that if we generate multiple demands, they follow the logical construction order
        $items = $this->db->query(
            "SELECT stage_name, percentage, stage_order FROM stage_of_work_items WHERE stage_of_work_id = ? ORDER BY stage_order ASC", 
            [$stageOfWorkId]
        )->fetchAll();

        // 3. Match and generate demands
        $secondsOffset = 0;
        foreach ($items as $item) {
            // Check if this stage is actually completed for the project
            if (in_array($item['stage_name'], $completedStages)) {
                $amount = round(($agreementValue * $item['percentage']) / 100);
                
                // Stagger the timestamps by 1 second each to ensure correct ordering in reports/prints
                // This allows 'Previous Dues' logic to work correctly based on time
                $generatedDate = date('Y-m-d H:i:s', time() + $secondsOffset);
                $secondsOffset++; 

                $demandData = [
                    'booking_id' => $bookingId,
                    'stage_name' => $item['stage_name'],
                    'demand_amount' => $amount,
                    'paid_amount' => 0.00,
                    'status' => 'pending',
                    'due_date' => date('Y-m-d'), // Immediate due as stage is already done
                    'generated_date' => $generatedDate,
                    'notes' => 'Auto-generated catch-up for completed project milestone'
                ];
                
                $this->db->insert('booking_demands', $demandData);
            }
        }
    }
}
