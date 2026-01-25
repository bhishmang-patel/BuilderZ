# Financial Reports - CORRECT Profit/Loss Calculations

## ✅ **ALL REPORTS FIXED**

All financial reports now correctly handle **cancellation income** and **customer refunds** for accurate profit/loss calculations.

---

## 🔧 **What Was Fixed**

### **1. Project P&L Report** ✅ FIXED

#### **Before (WRONG):**
```
Income:
- Total Sales (active bookings only)
- Total Received (all payments)

Expenses:
- Material Cost
- Labour Cost
❌ MISSING: Vendor Payments
❌ MISSING: Customer Refunds

Profit:
❌ Net Profit = Received - (Material + Labour)
```

#### **After (CORRECT):**
```
Income:
✅ Total Sales (active bookings)
✅ Total Received (customer receipts)
✅ Cancellation Income (deduction charges) ← NEW
✅ Total Income = Received + Cancellation Income

Expenses:
✅ Material Cost
✅ Labour Cost
✅ Vendor Payments ← ADDED
✅ Customer Refunds ← ADDED
✅ Total Expense = Material + Labour + Vendor + Refunds

Profit:
✅ Gross Profit = Sales - (Material + Labour + Vendor)
✅ Net Profit = Total Income - Total Expense
✅ Net Profit = (Received + Canc. Income) - (Material + Labour + Vendor + Refunds)
```

---

### **2. Cash Flow Report** ✅ FIXED

#### **Before (WRONG):**
```
Outflow = Vendor Payments + Labour Payments
❌ MISSING: Customer Refunds
```

#### **After (CORRECT):**
```
Inflow = Customer Receipts
Outflow = Vendor Payments + Labour Payments + Customer Refunds ← ADDED
Net Cash Flow = Inflow - Outflow
```

---

### **3. Dashboard** ✅ FIXED

#### **Before (WRONG):**
```
Total Expenses = Material + Labour Challans
❌ MISSING: Vendor Payments
❌ MISSING: Customer Refunds

Net Profit = Received - Expenses
❌ MISSING: Cancellation Income
```

#### **After (CORRECT):**
```
Income:
✅ Total Received
✅ Cancellation Income ← ADDED

Expenses:
✅ Material + Labour Challans
✅ Vendor Payments ← ADDED
✅ Customer Refunds ← ADDED

Net Profit = (Received + Cancellation Income) - (Challans + Vendor + Refunds)
```

---

### **4. Payment Register** ✅ ALREADY CORRECT

```
✅ Customer Receipts (Income)
✅ Customer Refunds (Expenditure)
✅ Cancellation Income (from financial_transactions)
✅ Net Cash Flow = Receipts - (Payments + Refunds)
✅ Net Income = Receipts + Canc. Income - (Payments + Refunds)
```

---

### **5. Income & Expenditure Report** ✅ ALREADY CORRECT

```
Income:
✅ Customer Receipts
✅ Cancellation Charges

Expenditure:
✅ Vendor Payments
✅ Labour Payments
✅ Customer Refunds

Net Profit = Total Income - Total Expenditure
```

---

## 💰 **Correct Calculation Formula**

### **For ALL Reports:**

```
TOTAL INCOME:
= Customer Receipts
+ Cancellation Charges (deductions kept)

TOTAL EXPENDITURE:
= Material Cost
+ Labour Cost
+ Vendor Payments
+ Customer Refunds (money returned)

NET PROFIT/LOSS:
= TOTAL INCOME - TOTAL EXPENDITURE
= (Receipts + Cancellation Income) - (Material + Labour + Vendor + Refunds)
```

---

## 📊 **Example Scenario**

### **Booking Details:**
- Agreement Value: ₹10,00,000
- Customer Paid: ₹3,00,000
- **Cancelled:**
  - Refund Given: ₹2,50,000
  - Deduction Kept: ₹50,000

### **Project Expenses:**
- Material: ₹2,00,000
- Labour: ₹1,50,000
- Vendor: ₹1,00,000

### **CORRECT Calculations:**

```
INCOME:
Customer Receipts:      ₹3,00,000
Cancellation Income:    +   50,000
------------------------
Total Income:           ₹3,50,000

EXPENDITURE:
Material Cost:          ₹2,00,000
Labour Cost:            ₹1,50,000
Vendor Payments:        ₹1,00,000
Customer Refunds:       ₹2,50,000
------------------------
Total Expenditure:      ₹7,00,000

NET PROFIT/LOSS:
= ₹3,50,000 - ₹7,00,000
= -₹3,50,000 (LOSS)
```

---

## ✅ **Where Cancellation Data Appears**

### **Cancellation Income (Deductions):**
✅ Project P&L - Separate "Canc. Income" column
✅ Income & Expenditure - Income category breakdown
✅ Payment Register - "Cancellation Income" stat card
✅ Dashboard - Included in Net Profit calculation

### **Customer Refunds:**
✅ Project P&L - "Refunds" column in Expenses
✅ Income & Expenditure - Expenditure category breakdown
✅ Payment Register - "Refunds" column + stat card
✅ Cash Flow - Included in Outflow
✅ Dashboard - Included in Total Expenses

---

## 🎯 **Report-wise Breakdown**

### **Project P&L Columns:**

| Income Columns | Expense Columns | Profit Columns |
|----------------|-----------------|----------------|
| Sales | Material | Gross Profit |
| Received | Labour | Net Profit |
| **Canc. Income** ← NEW | Vendor | Margin % |
| Pending | **Refunds** ← NEW | |
| | Total | |

### **Summary Shows:**
- Total Income = Received + Cancellation Income
- Total Expense = Material + Labour + Vendor + Refunds
- Net Profit = Total Income - Total Expense

---

## 📝 **Files Modified**

1. ✅ `modules/reports/project_pl.php` - **FIXED**
2. ✅ `modules/reports/cash_flow.php` - **FIXED**
3. ✅ `modules/dashboard/index.php` - **FIXED**
4. ✅ `modules/reports/payment_register.php` - Already correct
5. ✅ `modules/reports/income_expenditure.php` - Already correct

---

## ✨ **Key Changes Made**

### **Project P&L:**
- Added `cancellation_income` subquery
- Added `total_refunds` subquery
- Added `vendor_payments` subquery
- Recalculated `total_income` = received + cancellation_income
- Recalculated `total_expense` = material + labour + vendor + refunds
- Recalculated `net_profit` = total_income - total_expense
- Added new columns in table display
- Updated summary boxes with correct breakdowns

### **Cash Flow:**
- Updated outflow query to include `customer_refund`
- Now shows: `WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund')`

### **Dashboard:**
- Added cancellation_income query
- Added vendor_payments query
- Added total_refunds query
- Recalculated total_expenses to include all
- Recalculated net_profit with cancellation income
- Updated monthly chart to include refunds in expenses

---

## ✅ **Verification Checklist**

- [x] Cancellation income added to income
- [x] Customer refunds added to expenditure
- [x] Vendor payments included in expenses
- [x] Net profit formula correct
- [x] All reports show consistent data
- [x] Compact, professional UI maintained
- [x] Audit-ready presentation

---

## 🎉 **Result**

**ALL FINANCIAL REPORTS NOW SHOW CORRECT PROFIT/LOSS!**

✅ Cancellation income properly added to income  
✅ Customer refunds properly deducted from profit  
✅ All expenses accounted for  
✅ Consistent calculations across all reports  
✅ Audit-ready and accurate  

---

**Status:** ✅ **COMPLETE & VERIFIED**
