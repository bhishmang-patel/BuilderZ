-- Create project_budgets table
CREATE TABLE IF NOT EXISTS project_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category_id INT NOT NULL,
    budget_amount DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE,
    UNIQUE KEY project_category (project_id, category_id)
);
