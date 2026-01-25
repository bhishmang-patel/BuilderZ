# Booking Cancellation & Refund Management System

## Overview
A comprehensive booking cancellation and refund management system has been implemented with complete financial tracking and automated record updates.

## Features Implemented

### 1. **Booking Cancellation Page** (`modules/booking/cancel.php`)
- **Payment History Display**: Shows all installments/payments received for the booking
- **Refund Calculator**: Real-time calculation of refund amount based on deductions
- **Deduction Management**: Ability to specify cancellation charges/deductions
- **Flexible Refund Options**: Support for cash, bank transfer, UPI, and cheque
- **Reason Tracking**: Capture cancellation reason and deduction justification
- **Automatic Updates**: 
  - Booking status changed to 'cancelled'
  - Flat status reverted to 'available'
  - Financial records automatically updated

### 2. **Cancellation Details View** (`modules/booking/cancellation_details.php`)
- Complete cancellation information display
- Summary cards showing:
  - Agreement Value
  - Total Received
  - Deduction Charges
  - Refund Amount
- Full payment history
- Customer and booking information
- Downloadable cancellation receipt

### 3. **Cancelled Bookings List** (`modules/booking/cancelled.php`)
- Comprehensive list of all cancelled bookings
- Statistics dashboard showing:
  - Total number of cancellations
  - Total cancelled value
  - Total refunded amount
  - Total deduction charges
- Sortable and filterable table
- Quick access to cancellation details

### 4. **Financial Management Integration**

#### New Database Tables:

**booking_cancellations**
- Stores complete cancellation details
- Tracks refund amounts and deduction charges
- Links to original booking
- Maintains audit trail

**financial_transactions**
- Tracks all income and expenditure
- Categories for different transaction types
- Project-wise financial tracking
- Supports cancellation charges as income

#### Updated Tables:
**payments**
- Added 'customer_refund' payment type
- Added 'booking_cancellation' reference type
- Supports negative transactions (refunds)

## How It Works

### Cancellation Process:

1. **Navigate to Booking**
   - Go to any active booking details page
   - Click "Cancel Booking" button in Quick Actions

2. **Review Payment History**
   - System displays all payments received
   - Shows total amount paid

3. **Calculate Refund**
   - Enter deduction/cancellation charges
   - System automatically calculates refund amount
   - Or enter refund amount, and deduction is calculated
   - Formula: `Refund Amount = Total Paid - Deduction`

4. **Provide Details**
   - Select cancellation reason
   - Specify deduction reason
   - Choose refund mode
   - Add transaction reference
   - Include additional remarks

5. **Confirm Cancellation**
   - Review all details
   - Confirm the action
   - System processes cancellation

### Automatic Updates:

When a booking is cancelled, the system automatically:

1. **Updates Booking Status**
   - Changes booking status to 'cancelled'
   - Preserves all historical data

2. **Releases Flat**
   - Changes flat status back to 'available'
   - Makes it available for new bookings

3. **Records Refund**
   - Creates refund payment entry
   - Links to cancellation record
   - Updates payment register

4. **Records Income**
   - Adds deduction amount as income
   - Category: 'cancellation_charges'
   - Updates profit & loss statements

5. **Maintains Audit Trail**
   - Logs all changes
   - Tracks who processed the cancellation
   - Timestamps all actions

## Financial Impact

### Income/Expenditure Tracking:

**Income (Deduction Charges)**
- Cancellation charges are recorded as income
- Category: `cancellation_charges`
- Linked to specific project
- Included in profit calculations

**Expenditure (Refunds)**
- Refunds are recorded as customer refunds
- Tracked separately from regular payments
- Reduces total received amount
- Updates cash flow reports

### Profit & Loss Impact:

The system updates the following financial records:

1. **Project P&L Report**
   - Deduction charges added to project income
   - Refunds tracked as expenditure
   - Net impact calculated automatically

2. **Cash Flow**
   - Refund outflows recorded
   - Deduction inflows recorded
   - Running balance maintained

3. **Payment Register**
   - All refunds listed separately
   - Filterable by payment type
   - Downloadable reports

## Access Points

### From Booking View:
- "Cancel Booking" button (only for active bookings)

### From Bookings List:
- "View Cancelled" button to see all cancellations

### From Cancellation Details:
- "Download Receipt" for PDF documentation

## Reports & Documentation

### Available Reports:
1. **Cancellation Receipt** (PDF)
   - Complete cancellation details
   - Payment history
   - Refund breakdown
   - Company letterhead

2. **Cancelled Bookings Summary**
   - Statistics dashboard
   - Detailed listing
   - Export capabilities

## Security & Permissions

- Only authenticated users can cancel bookings
- Cancellation cannot be undone
- Complete audit trail maintained
- User who processed cancellation is recorded

## Data Integrity

### Safeguards:
- Foreign key constraints prevent data loss
- Booking cannot be deleted if cancelled
- Payment history preserved
- Financial records immutable

### Validation:
- Refund + Deduction must equal Total Paid
- Cannot refund more than received
- All amounts must be non-negative
- Required fields enforced

## Usage Tips

1. **Before Cancelling**:
   - Review all payment history
   - Verify customer details
   - Calculate appropriate deduction
   - Prepare refund documentation

2. **Deduction Guidelines**:
   - Administrative charges
   - Processing fees
   - Time-based penalties
   - Agreement terms

3. **Refund Processing**:
   - Choose appropriate refund mode
   - Record transaction reference
   - Document the process
   - Inform customer

4. **Record Keeping**:
   - Download cancellation receipt
   - Maintain physical copies
   - Update customer records
   - File for audit purposes

## Integration with Existing Features

### Dashboard:
- Cancelled bookings excluded from active count
- Deduction charges included in income
- Refunds included in expenditure
- Net profit calculations updated

### Reports:
- Customer Pending: Excludes cancelled bookings
- Project P&L: Includes cancellation charges
- Payment Register: Shows refunds separately
- Cash Flow: Tracks refund outflows

### Masters:
- Flats: Status updated to available
- Customers: History preserved
- Projects: Financial impact recorded

## Technical Details

### Database Schema:
```sql
booking_cancellations:
- id, booking_id, cancellation_date
- total_paid, refund_amount, deduction_amount
- deduction_reason, refund_mode, refund_reference
- cancellation_reason, remarks, processed_by

financial_transactions:
- id, transaction_type, category
- reference_type, reference_id, project_id
- transaction_date, amount, description
```

### Key Functions:
- `calculateRefund()`: Real-time refund calculation
- `validateCancellation()`: Form validation
- `updateBookingTotals()`: Financial updates
- `logAudit()`: Audit trail maintenance

## Future Enhancements

Potential additions:
- Partial cancellations
- Installment-wise refunds
- Automated refund processing
- Email notifications
- SMS alerts
- Approval workflow
- Refund scheduling

## Support

For issues or questions:
1. Check audit trail for transaction history
2. Review cancellation details page
3. Verify financial transaction records
4. Contact system administrator

---

**Version**: 1.0  
**Last Updated**: January 2026  
**Module**: Booking Management  
**Status**: Production Ready
