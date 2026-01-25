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
            $rate = isset($data['rate']) ? floatval($data['rate']) : 0.00;
            $stamp_duty_registration = isset($data['stamp_duty_registration']) ? floatval($data['stamp_duty_registration']) : 0.00;
            $registration_amount = isset($data['registration_amount']) ? floatval($data['registration_amount']) : 0.00;
            $gst_amount = isset($data['gst_amount']) ? floatval($data['gst_amount']) : 0.00;
            $development_charge = isset($data['development_charge']) ? floatval($data['development_charge']) : 0.00;
            $parking_charge = isset($data['parking_charge']) ? floatval($data['parking_charge']) : 0.00;
            $society_charge = isset($data['society_charge']) ? floatval($data['society_charge']) : 0.00;
            
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
                'status' => 'active',
                'created_by' => $userId
            ];
            
            $booking_id = $this->db->insert('bookings', $booking_data);
            
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
}
