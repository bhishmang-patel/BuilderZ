<?php
// Mock Data based on User Example
$agreementValue = 2150040;
$devCharge = 300000;
$parkingCharge = 0;
$societyCharge = 0;
$area = 1000; // Hypothetical area for rate check

echo "--- Calculation Verification Script ---\n";
echo "Agreement Value: $agreementValue\n";
echo "Development Charge: $devCharge\n";

// 1. Stamp Duty (6%)
$stampDuty = round($agreementValue * 0.06);
echo "Stamp Duty (6%): $stampDuty (Expected: 129002)\n";

// 2. Registration (1% capped at 30k)
$registration = round($agreementValue * 0.01);
if ($agreementValue >= 3000000) {
    $registration = 30000;
}
echo "Registration: $registration (Expected: 21500)\n"; // 2150040 * 0.01 = 21500.4 -> 21500

// 3. GST (1%)
$gst = round($agreementValue * 0.01);
echo "GST (1%): $gst (Expected: 21500)\n";

// 4. Total Cost Calculation (Net)
// User Formula: Agreement - Charges - Stamp - Reg - GST
$charges = $devCharge + $parkingCharge + $societyCharge;
$totalCost = $agreementValue - $charges - $stampDuty - $registration - $gst;

echo "Total Cost: $totalCost (Expected: 1678038)\n"; 
// Note: User example says 16,78,037. Let's check why.
// 2150040 - 300000 - 129002 - 21500 - 21500 = 1678038.
// User might have rounded differently or typo? 
// 129002.4 -> 129002
// 21500.4 -> 21500
// 21500.4 -> 21500
// Sum of deductions: 300000 + 129002 + 21500 + 21500 = 472002
// Net: 2150040 - 472002 = 1678038
// User example: 16,78,037. Difference of 1.
// Maybe user used floor/ceil or exact decimals?
// If we use exact:
// 2150040 - 300000 - (2150040*0.06) - (2150040*0.01) - (2150040*0.01)
// = 2150040 - 300000 - 129002.4 - 21500.4 - 21500.4
// = 1678036.8
// Rounding that gives 1678037. So user logic is "Calculate using floats -> Subtract -> Round Final".
// My JS/PHP logic was "Round Each Tax -> Subtract".
// Let's adjust algorithm if needed to match 1678037. 
// Actually, in JS `Math.round` matches PHP `round`.
// If I use `toFixed(0)` or similar it might differ.
// Let's print float results to see.

// 5. Rate
$rate = $totalCost / $area;
echo "Rate: " . number_format($rate, 2) . "\n";

echo "--- High Value Test (Reg Cap) ---\n";
$highVal = 5000000;
$regHigh = ($highVal >= 3000000) ? 30000 : round($highVal * 0.01);
echo "Agreement: $highVal -> Reg: $regHigh (Expected: 30000)\n";
