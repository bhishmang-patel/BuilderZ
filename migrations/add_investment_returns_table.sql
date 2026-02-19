CREATE TABLE IF NOT EXISTS investment_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investment_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    return_date DATE NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE
);
