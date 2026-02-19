CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type VARCHAR(50) DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read)
);
