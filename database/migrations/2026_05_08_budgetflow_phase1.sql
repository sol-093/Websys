-- INVOLVE BudgetFlow Phase 1 database foundation.
-- Safe to re-run on MySQL/MariaDB.

SET @budgetflow_expense_request_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'financial_transactions'
      AND COLUMN_NAME = 'expense_request_id'
);

SET @budgetflow_add_expense_request_column_sql := IF(
    @budgetflow_expense_request_column_exists = 0,
    'ALTER TABLE financial_transactions ADD COLUMN expense_request_id INT NULL',
    'SELECT "financial_transactions.expense_request_id already exists"'
);

PREPARE budgetflow_add_expense_request_column_stmt FROM @budgetflow_add_expense_request_column_sql;
EXECUTE budgetflow_add_expense_request_column_stmt;
DEALLOCATE PREPARE budgetflow_add_expense_request_column_stmt;

SET @budgetflow_expense_request_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'financial_transactions'
      AND INDEX_NAME = 'idx_tx_expense_request'
);

SET @budgetflow_add_expense_request_index_sql := IF(
    @budgetflow_expense_request_index_exists = 0,
    'CREATE INDEX idx_tx_expense_request ON financial_transactions (expense_request_id)',
    'SELECT "idx_tx_expense_request already exists"'
);

PREPARE budgetflow_add_expense_request_index_stmt FROM @budgetflow_add_expense_request_index_sql;
EXECUTE budgetflow_add_expense_request_index_stmt;
DEALLOCATE PREPARE budgetflow_add_expense_request_index_stmt;

CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    created_by INT NULL,
    title VARCHAR(191) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budgets_org FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_budgets_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_budgets_org_status (organization_id, status),
    INDEX idx_budgets_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS budget_line_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    category_name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    spent_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_lines_budget FOREIGN KEY (budget_id)
        REFERENCES budgets(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_budget_lines_budget (budget_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expense_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    budget_id INT NOT NULL,
    budget_line_item_id INT NOT NULL,
    requested_by INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    receipt_path VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    transaction_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_requests_org FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expense_requests_budget FOREIGN KEY (budget_id)
        REFERENCES budgets(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expense_requests_line FOREIGN KEY (budget_line_item_id)
        REFERENCES budget_line_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expense_requests_requester FOREIGN KEY (requested_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_expense_requests_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_expense_requests_transaction FOREIGN KEY (transaction_id)
        REFERENCES financial_transactions(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_expense_requests_org_status (organization_id, status),
    INDEX idx_expense_requests_line_status (budget_line_item_id, status),
    INDEX idx_expense_requests_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
