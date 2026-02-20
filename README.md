# �️ EstateAxis ERP - Ultimate Real Estate & Construction Management System

**Version**: 1.1 (Feb 2026)  
**Tech Stack**: PHP 8+, MySQL, Vanilla JS, CSS3 (Modern UI)  
**Target Audience**: Indian Real Estate Developers & Construction Firms

---

## 📖 Project Overview

**EstateAxis** is a production-ready, desktop-optimized ERP system designed to manage the entire lifecycle of a real estate project. From **Lead Generation (CRM)** to **Construction (Work Orders, Inventory)** to **Sales (Bookings, Payments)** and **Final Handover**, EstateAxis handles it all with a unified, professional interface.

It is purpose-built for the **Indian market**, featuring GST support, Indian currency formatting (`₹ 1,00,000`), TDS calculations, and local unit measurements (Sqft, Brass, CFT).

---

## 🚀 Key Features at a Glance

### 🏢 Core Modules
| Module | Description |
| :--- | :--- |
| **CRM & Leads** | Track inquiries, site visits, and convert leads to customers. |
| **Project Master** | Manage multiple projects, towers, and unit costs. |
| **Inventory Mgmt** | Material stock tracking, purchase challans, and consumption logs. |
| **Contractor Mgmt** | Work orders, running bills (RA Wills), TDS tracking, and retention. |
| **Sales & Booking** | Flat availability, booking agreements, payment schedules, and receipts. |
| **Finance** | Expense recording, payment vouchers, partner investments, and P&L reports. |

### ✨ Premium Features
- **Project P&L Report**: Real-time profit/loss analysis with animated charts.
- **Bulk Flat Creation**: Generate 100+ flats (e.g., A-101 to A-1004) in seconds.
- **Audit Reports**: CA-ready exports for sales, expenses, and GST returns.
- **Role-Based Security**: Admin, Project Manager, Accountant, and Sales access levels.

---

## 📚 End-to-End Module Guide

### 1. 🎛️ Dashboard
The central command center providing a snapshot of your business health.
- **Financial Cards**: Total Sales, Total Received, Total Outstanding, and Net Profit.
- **Live Activity**: Recent bookings and pending approvals.
- **Quick Actions**: Shortcuts to adding leads, expenses, or bookings.

### 2. 🤝 CRM (Customer Relationship Management)
*Located in: `modules/crm/`*
Stop losing sales on spreadsheets. Track every potential buyer.
- **Lead Stages**: New → Follow-up → Site Visit → Interested → Booked → Lost.
- **Lead Details**: Capture name, mobile, email, budget, and source (e.g., "Newspaper Ad").
- **Filters**: Quickly filter leads by "Hot" interest level or "Site Visit" status.

### 3. 🏗️ Projects & Inventory
*Located in: `modules/projects/`, `modules/inventory/`, `modules/masters/`*
Manage the construction side of operations.

#### **Project Setup**
- Create **Projects** (e.g., "Sunrise Apartments").
- Configure **Towers** and **Flats**.
- **Bulk Create**: Use the bulk tool to create flats A-101 through A-1004 automatically based on floor count and flats-per-floor.

#### **Material Management**
- **Material Master**: Pre-loaded with Cement (Bag), Steel (Kg), Sand (Brass), Bricks (Nos), etc.
- **Purchase (Inward)**: Create **Material Challans** when goods arrive at the site. This **automatically increases stock**.
- **Consumption (Outward)**: Use the **Usage** form to record material used (e.g., "50 Bags Cement used for Foundation"). This **decreases stock**.
- **Stock Alerts**: System notifies when stock dips below minimum levels.

### 4. 👷 Contractors & Work Orders
*Located in: `modules/contractors/`*
Streamline labour management and billing.
- **Work Orders (WO)**: Issue formal contracts to contractors (e.g., "Brickwork at ₹18/sqft").
- **Running Bills**: Generate bills against active WOs. The system tracks:
    - **Total Work Done**
    - **Previous Paid Amount**
    - **TDS Deduction** (e.g., 1% or 2%)
    - **Net Payable**
- **Contractor Ledger**: View complete history of work done vs. payments made.

### 5. � Sales & Bookings
*Located in: `modules/booking/`*
The revenue engine.
- **Interactive Flat List**: Color-coded status (Green=Available, Red=Sold, Yellow=Booked).
- **Booking Flow**:
    1. Select a Lead/Customer.
    2. Select a valid Flat.
    3. Enter **Agreement Value** and **Booking Date**.
    4. System changes Flat status to **Booked**.
- **Payments**: Record payments against bookings (Cheque/NEFT/Cash).
- **Payment Plan**: Track how much is received vs. pending for each customer.

### 6. 🧾 Accounts & Finance
*Located in: `modules/accounts/`, `modules/investments/`*
Keep your books clean.
- **Expense Recording**: Log daily office expenses, tea/coffee, salary, etc.
- **Payment Vouchers**: Record payments made to Vendors and Contractors.
- **Investments**: Track capital brought in by partners or loans from banks.
- **Reports**:
    - **Day Book**: Daily cash/bank flow.
    - **Ledgers**: Party-wise statements.
    - **Project P&L**: Detailed Profit & Loss per project.

### 7. 🔒 Admin & Security
*Located in: `modules/admin/`*
- **User Management**: Create users and assign roles.
- **Audit Trail**: View a log of *who* did *what* and *when* (e.g., "User 'Raj' deleted Payment #104").

---

## � Technical Installation Guide

### Prerequisites
- **OS**: Windows 10/11 (Preferred)
- **Web Server**: XAMPP (Apache + MySQL + PHP)
- **PHP Version**: 8.0, 8.1, or 8.2

### Step-by-Step Installation

1.  **Download XAMPP** from [apachefriends.org](https://www.apachefriends.org/) and install it.
2.  **Start Services**: Open XAMPP Control Panel and start **Apache** and **MySQL**.
3.  **Deploy Code**:
    - Project folder: `builderz`
    - Copy to: `C:\xampp\htdocs\`
    - Resulting path: `C:\xampp\htdocs\builderz\`
4.  **Run Installer**:
    - Open Chrome/Edge.
    - Go to: `http://localhost/builderz/config/install.php`
    - Click **"Install Database"**.
    - *Success! User tables created.*

### Login Credentials
- **URL**: `http://localhost/builderz/`
- **Username**: `admin`
- **Password**: `admin123`
- *Note: Please change password after first login via Profile settings.*

---

## 📁 Directory Structure

```plaintext
builderz/
├── assets/                 # CSS (style.css), JS, Images, Fonts
├── backups/                # Database SQL backups
├── config/                 # Configuration files
│   ├── database.php        # DB Connection Class
│   ├── config.php          # App constants (BASE_URL)
│   ├── schema.sql          # Database structure
│   └── install.php         # One-click installer
├── includes/               # Reusable PHP components
│   ├── auth.php            # Security & Session checks
│   ├── header.php          # Top navigation bar
│   ├── footer.php          # Closing tags & scripts
│   └── functions.php       # Helper functions
├── modules/                # CORE FUNCTIONALITY
│   ├── accounts/           # Expense & General Ledger
│   ├── admin/              # User Mgmt, Audit Logs
│   ├── auth/               # Login, Logout
│   ├── booking/            # Sales, Payment Collection
│   ├── contractors/        # Work Orders, Bills
│   ├── crm/                # Leads (New!)
│   ├── dashboard/          # Home screen stats
│   ├── inventory/          # Material Usage & Stock
│   ├── masters/            # Setup (Materials, Projects, Flats)
│   ├── payments/           # Payment History (Global)
│   └── reports/            # CA Exports, P&L
├── uploads/                # File storage (Docs, Images)
├── index.php               # Redirects to Login/Dashboard
└── README.md               # This file
```

---

## � Troubleshooting Common Issues

**1. "Database Connection Failed"**
- Check if MySQL is running in XAMPP.
- Verify credentials in `config/.env` or `database.php` (Default: `root` / empty password).

**2. "Exported CSV is empty"**
- In the Audit Export page, check the **Month** selected. The system defaults to *Last Month*. If you entered data today, select *Current Month*.

**3. "Dates show as ###### in Excel"**
- This is an Excel display issue. The column width is too narrow. Double-click the column header border to expand it.

---

**EstateAxis ERP** | Built for stability, speed, and scale.
