-- MySQL schema for Student Organization Management and Budget Transparency System

CREATE DATABASE IF NOT EXISTS websysdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE websysdb;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','student','owner') NOT NULL DEFAULT 'student',
    onboarding_done TINYINT(1) NOT NULL DEFAULT 0,
    institute VARCHAR(191) NULL,
    program VARCHAR(191) NULL,
    year_level TINYINT NULL,
    section VARCHAR(50) NULL,
    profile_picture_path VARCHAR(255) NULL,
    profile_picture_crop_x DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    profile_picture_crop_y DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    profile_picture_zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    email_verified TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    activation_token VARCHAR(64) NULL,
    activation_expires TIMESTAMP NULL DEFAULT NULL,
    reset_token VARCHAR(64) NULL,
    reset_expires TIMESTAMP NULL DEFAULT NULL,
    account_status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    last_login_ip VARCHAR(45) NULL,
    password_changed_at TIMESTAMP NULL DEFAULT NULL,
    password_reset_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL UNIQUE,
    description TEXT,
    logo_path VARCHAR(255) NULL,
    owner_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_organizations_owner FOREIGN KEY (owner_id)
        REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_org_member (organization_id, user_id),
    CONSTRAINT fk_members_org FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_members_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    label VARCHAR(80) NULL,
    duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    expires_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announce_org FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    type ENUM('income','expense') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    transaction_date DATE NOT NULL,
    receipt_path VARCHAR(255) NULL,
    expense_request_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_org FOREIGN KEY (organization_id)
        REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_tx_expense_request (expense_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    device_fingerprint VARCHAR(64) NULL,
    last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_remembered TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_session_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_session_user (user_id),
    INDEX idx_session_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_history_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_password_history_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
