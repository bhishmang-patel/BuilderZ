-- Expense Categories Table
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('expense', 'asset', 'liability') DEFAULT 'expense',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- EXPENSES Table
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    description TEXT,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'upi', 'card') DEFAULT 'cash',
    reference_no VARCHAR(100),
    gst_included BOOLEAN DEFAULT FALSE,
    gst_amount DECIMAL(10,2) DEFAULT 0.00,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Default Categories
INSERT INTO expense_categories (name, description) VALUES 
('Office Rent', 'Monthly office rent payment'),
('Electricity Bill', 'Office electricity bill'),
('Internet', 'Office internet bill'),
('Tea & Snacks', 'Daily office expenses'),
('Marketing', 'Brochures, Ads, etc.'),
('Stationery', 'Office supplies'),
('Fuel', 'Site visits fuel expenses'),
('Salaries - Staff', 'Office staff salaries'),
('Petty Cash', 'Miscellaneous small expenses');
