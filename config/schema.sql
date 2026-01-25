-- BuilderZ Database Schema
-- Real Estate Booking + Construction + Accounting

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'accountant', 'project_manager') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(200) NOT NULL,
    location VARCHAR(255),
    start_date DATE,
    expected_completion DATE,
    total_floors INT DEFAULT 0,
    total_flats INT DEFAULT 0,
    status ENUM('active', 'completed', 'on_hold') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Flats Table
CREATE TABLE IF NOT EXISTS flats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    flat_no VARCHAR(50) NOT NULL,
    floor INT NOT NULL,
    bhk VARCHAR(10) NOT NULL COMMENT '1BHK, 2BHK, 3BHK',
    area_sqft DECIMAL(10,2) NOT NULL,
    rate_per_sqft DECIMAL(10,2) NOT NULL,
    total_value DECIMAL(12,2) GENERATED ALWAYS AS (area_sqft * rate_per_sqft) STORED,
    status ENUM('available', 'booked', 'sold') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_flat (project_id, flat_no),
    INDEX idx_status (status),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parties Table (Unified: Customer/Vendor/Labour)
CREATE TABLE IF NOT EXISTS parties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_type ENUM('customer', 'vendor', 'labour') NOT NULL,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    mobile VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    gst_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_party_type (party_type),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flat_id INT NOT NULL,
    customer_id INT NOT NULL,
    project_id INT NOT NULL,
    agreement_value DECIMAL(12,2) NOT NULL,
    booking_date DATE NOT NULL,
    total_received DECIMAL(12,2) DEFAULT 0,
    total_pending DECIMAL(12,2) GENERATED ALWAYS AS (agreement_value - total_received) STORED,
    status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    FOREIGN KEY (flat_id) REFERENCES flats(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES parties(id) ON DELETE RESTRICT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materials Master Table
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(200) NOT NULL,
    unit ENUM('kg', 'ton', 'bag', 'cft', 'sqft', 'nos', 'ltr', 'brass', 'bundle') NOT NULL,
    default_rate DECIMAL(10,2),
    current_stock DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (material_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Challans Table
CREATE TABLE IF NOT EXISTS challans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challan_no VARCHAR(50) UNIQUE NOT NULL,
    challan_type ENUM('material', 'labour', 'customer') NOT NULL,
    party_id INT NOT NULL,
    project_id INT NOT NULL,
    challan_date DATE NOT NULL,
    work_description TEXT,
    work_from_date DATE,
    work_to_date DATE,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    pending_amount DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    status ENUM('pending', 'partial', 'paid', 'approved') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_challan_no (challan_no),
    INDEX idx_type (challan_type),
    INDEX idx_status (status),
    FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE RESTRICT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Challan Items Table (for material challans)
CREATE TABLE IF NOT EXISTS challan_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challan_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(12,2) GENERATED ALWAYS AS (quantity * rate) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challan_id) REFERENCES challans(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_type ENUM('customer_receipt', 'vendor_payment', 'labour_payment') NOT NULL,
    reference_type ENUM('booking', 'challan') NOT NULL,
    reference_id INT NOT NULL,
    party_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode ENUM('cash', 'bank', 'upi', 'cheque') NOT NULL,
    reference_no VARCHAR(100),
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_type (payment_type),
    INDEX idx_reference (reference_type, reference_id),
    FOREIGN KEY (party_id) REFERENCES parties(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Trail Table
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action ENUM('create', 'update', 'delete', 'approve', 'login', 'logout') NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
