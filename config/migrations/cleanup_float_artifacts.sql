-- Cleanup Float Artifacts
-- Update existing records to round off the .9999 float artifacts to nearest integer (or 2 decimal places if valid)
-- Since generic float errors usually result in .99 or .000001, ROUND() is safe.

UPDATE bookings SET 
    agreement_value = ROUND(agreement_value),
    total_received = ROUND(total_received),
    total_pending = ROUND(total_pending);

UPDATE booking_demands SET 
    demand_amount = ROUND(demand_amount),
    paid_amount = ROUND(paid_amount);

UPDATE payments SET 
    amount = ROUND(amount);

UPDATE flats SET 
    total_value = ROUND(total_value);
