<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - DATABASE BOOTSTRAP
 * ================================================
 *
 * SECTION MAP:
 * 1. PDO Connection
 * 2. Baseline Schema Bootstrap
 * 3. Compatibility Migrations
 *
 * WORK GUIDE:
 * - Edit this file for DB connection or schema compatibility changes.
 * ================================================
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $dbConfig = $config['db'] ?? [];
    $driver = (string) ($dbConfig['driver'] ?? 'sqlite');

    if ($driver === 'mysql') {
        $mysql = resolveMySqlConnectionConfig($dbConfig);
        $host = (string) ($mysql['host'] ?? '127.0.0.1');
        $port = (int) ($mysql['port'] ?? 3306);
        $database = (string) ($mysql['database'] ?? 'websysdb');
        $username = (string) ($mysql['username'] ?? 'root');
        $password = (string) ($mysql['password'] ?? '');
        $bootstrapDatabase = (bool) ($mysql['bootstrap_database'] ?? true);

        if ($bootstrapDatabase && $database !== '') {
            try {
                $bootstrapDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
                $bootstrapPdo = new PDO($bootstrapDsn, $username, $password, [
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $bootstrapPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                if (!isCreateDatabasePermissionError($e)) {
                    throw $e;
                }
            }
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        applyMySqlSessionTimezone($pdo, (string) ($config['timezone'] ?? 'Asia/Manila'));

        initializeDatabaseMySql($pdo);
    } else {
        $dbPath = (string) ($dbConfig['sqlite_path'] ?? (__DIR__ . '/../storage/database.sqlite'));

        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        initializeDatabaseSqlite($pdo);
    }

    return $pdo;
}

function resolveMySqlConnectionConfig(array $dbConfig): array
{
    $config = [
        'host' => (string) ($dbConfig['host'] ?? '127.0.0.1'),
        'port' => (int) ($dbConfig['port'] ?? 3306),
        'database' => (string) ($dbConfig['database'] ?? 'websysdb'),
        'username' => (string) ($dbConfig['username'] ?? 'root'),
        'password' => (string) ($dbConfig['password'] ?? ''),
        'bootstrap_database' => (bool) ($dbConfig['bootstrap_database'] ?? true),
    ];

    $dsn = getenv('DATABASE_URL');
    if ($dsn === false || $dsn === '') {
        $dsn = getenv('MYSQL_URL');
    }

    if (is_string($dsn) && $dsn !== '') {
        $parts = parse_url($dsn);
        if (is_array($parts) && (($parts['scheme'] ?? '') === 'mysql')) {
            $config['host'] = (string) ($parts['host'] ?? $config['host']);
            $config['port'] = (int) ($parts['port'] ?? $config['port']);
            $config['username'] = (string) ($parts['user'] ?? $config['username']);
            $config['password'] = (string) ($parts['pass'] ?? $config['password']);

            $path = (string) ($parts['path'] ?? '');
            $database = ltrim($path, '/');
            if ($database !== '') {
                $config['database'] = $database;
            }
        }
    }

    return $config;
}

function applyMySqlSessionTimezone(PDO $pdo, string $timezone): void
{
    try {
        $stmt = $pdo->prepare('SET time_zone = ?');
        $stmt->execute([$timezone]);
        return;
    } catch (Throwable) {
        // Fallback to a numeric offset when timezone tables are unavailable.
    }

    try {
        $offset = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('P');
        $stmt = $pdo->prepare('SET time_zone = ?');
        $stmt->execute([$offset]);
    } catch (Throwable) {
        // Leave the server default unchanged if both strategies fail.
    }
}

function isCreateDatabasePermissionError(PDOException $e): bool
{
    $message = strtolower($e->getMessage());

    if (!str_contains($message, 'create database')) {
        return false;
    }

    return str_contains($message, 'access denied')
        || str_contains($message, 'not allowed')
        || str_contains($message, 'command denied')
        || str_contains($message, 'permission');
}

function initializeDatabaseSqlite(PDO $pdo): void
{
    $schema = <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'student' CHECK(role IN ('admin','student','owner')),
        onboarding_done INTEGER NOT NULL DEFAULT 0,
        institute TEXT NULL,
        program TEXT NULL,
        year_level INTEGER NULL,
        section TEXT NULL,
        profile_picture_path TEXT NULL,
        profile_picture_crop_x REAL NOT NULL DEFAULT 50,
        profile_picture_crop_y REAL NOT NULL DEFAULT 50,
        profile_picture_zoom REAL NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS organizations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT DEFAULT '',
        org_category TEXT NOT NULL DEFAULT 'collegewide',
        target_institute TEXT NULL,
        target_program TEXT NULL,
        owner_id INTEGER NULL,
        logo_path TEXT NULL,
        logo_crop_x REAL NOT NULL DEFAULT 50,
        logo_crop_y REAL NOT NULL DEFAULT 50,
        logo_zoom REAL NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS organization_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        joined_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(organization_id, user_id),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS announcements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        label TEXT NULL,
        duration_days INTEGER NOT NULL DEFAULT 30,
        expires_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS financial_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL,
        type TEXT NOT NULL CHECK(type IN ('income','expense')),
        amount REAL NOT NULL,
        description TEXT NOT NULL,
        transaction_date TEXT NOT NULL,
        receipt_path TEXT NULL,
        expense_request_id INTEGER NULL,
        is_voided INTEGER NOT NULL DEFAULT 0,
        voided_at TEXT NULL,
        void_reason TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS budgets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL,
        created_by INTEGER NULL,
        title TEXT NOT NULL,
        period_start TEXT NOT NULL,
        period_end TEXT NOT NULL,
        total_amount REAL NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','active','closed')),
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS budget_line_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        budget_id INTEGER NOT NULL,
        category_name TEXT NOT NULL,
        description TEXT NULL,
        allocated_amount REAL NOT NULL DEFAULT 0,
        spent_amount REAL NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS expense_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL,
        budget_id INTEGER NOT NULL,
        budget_line_item_id INTEGER NOT NULL,
        requested_by INTEGER NULL,
        amount REAL NOT NULL,
        description TEXT NOT NULL,
        receipt_path TEXT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
        admin_note TEXT NULL,
        reviewed_by INTEGER NULL,
        reviewed_at TEXT NULL,
        transaction_id INTEGER NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
        FOREIGN KEY (budget_line_item_id) REFERENCES budget_line_items(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS owner_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL UNIQUE,
        student_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','accepted','declined')),
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS organization_join_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','declined')),
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(organization_id, user_id),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS transaction_change_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id INTEGER NOT NULL,
        organization_id INTEGER NOT NULL,
        requested_by INTEGER NOT NULL,
        action_type TEXT NOT NULL CHECK(action_type IN ('update','delete')),
        proposed_type TEXT NULL CHECK(proposed_type IN ('income','expense')),
        proposed_amount REAL NULL,
        proposed_description TEXT NULL,
        proposed_transaction_date TEXT NULL,
        status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
        admin_note TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id INTEGER NULL,
        details TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );
    SQL;

    $pdo->exec($schema);
    ensureAcademicAndVisibilityColumns($pdo);
    ensureAnnouncementPinColumns($pdo);
    ensureAnnouncementLifecycleColumns($pdo);
    ensureAuthEnhancementColumns($pdo);
    ensureProfileMediaColumns($pdo);
    ensureAuthEnhancementTables($pdo);
    ensureBudgetFlowSchema($pdo);
    ensurePerformanceIndexes($pdo);
    ensureDefaultAdmin($pdo);
    normalizeLegacyUploadPaths($pdo);
}

function initializeDatabaseMySql(PDO $pdo): void
{
    $schema = <<<SQL
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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS organizations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL UNIQUE,
        description TEXT,
        org_category VARCHAR(50) NOT NULL DEFAULT 'collegewide',
        target_institute VARCHAR(191) NULL,
        target_program VARCHAR(191) NULL,
        owner_id INT NULL,
        logo_path VARCHAR(255) NULL,
        logo_crop_x DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        logo_crop_y DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        logo_zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00,
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
        is_voided TINYINT(1) NOT NULL DEFAULT 0,
        voided_at DATETIME NULL,
        void_reason TEXT NULL,
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

    CREATE TABLE IF NOT EXISTS owner_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        organization_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_owner_assignment_org (organization_id),
        CONSTRAINT fk_owner_assignment_org FOREIGN KEY (organization_id)
            REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_owner_assignment_student FOREIGN KEY (student_id)
            REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS organization_join_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        organization_id INT NOT NULL,
        user_id INT NOT NULL,
        status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_join_request_org_user (organization_id, user_id),
        CONSTRAINT fk_join_request_org FOREIGN KEY (organization_id)
            REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_join_request_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS transaction_change_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        organization_id INT NOT NULL,
        requested_by INT NOT NULL,
        action_type ENUM('update','delete') NOT NULL,
        proposed_type ENUM('income','expense') NULL,
        proposed_amount DECIMAL(12,2) NULL,
        proposed_description VARCHAR(255) NULL,
        proposed_transaction_date DATE NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_note TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_txcr_transaction FOREIGN KEY (transaction_id)
            REFERENCES financial_transactions(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_txcr_org FOREIGN KEY (organization_id)
            REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_txcr_requester FOREIGN KEY (requested_by)
            REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(100) NOT NULL,
        entity_id INT NULL,
        details TEXT NULL,
        ip_address VARCHAR(64) NULL,
        user_agent VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
        INDEX idx_audit_created_at (created_at),
        INDEX idx_audit_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL;

    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensureAcademicAndVisibilityColumns($pdo);
    ensureAnnouncementPinColumns($pdo);
    ensureAnnouncementLifecycleColumns($pdo);
    ensureAuthEnhancementColumns($pdo);
    ensureProfileMediaColumns($pdo);
    ensureAuthEnhancementTables($pdo);
    ensureBudgetFlowSchema($pdo);
    ensurePerformanceIndexes($pdo);
    ensureDefaultAdmin($pdo);
    normalizeLegacyUploadPaths($pdo);
}

function normalizeLegacyUploadPaths(PDO $pdo): void
{
    $columns = [
        ['users', 'profile_picture_path'],
        ['organizations', 'logo_path'],
        ['financial_transactions', 'receipt_path'],
    ];

    foreach ($columns as [$table, $column]) {
        if (!tableColumnExists($pdo, $table, $column)) {
            continue;
        }

        $pdo->exec(
            sprintf(
                "UPDATE %s SET %s = REPLACE(%s, 'public/uploads/', 'uploads/') WHERE %s LIKE 'public/uploads/%%'",
                $table,
                $column,
                $column,
                $column
            )
        );
    }

    if (tableColumnExists($pdo, 'users', 'profile_picture_path')) {
        $pdo->exec("UPDATE users SET profile_picture_path = REPLACE(profile_picture_path, 'uploads/user_', 'uploads/users/user_') WHERE profile_picture_path LIKE 'uploads/user_%'");
    }

    if (tableColumnExists($pdo, 'organizations', 'logo_path')) {
        $pdo->exec("UPDATE organizations SET logo_path = REPLACE(logo_path, 'uploads/org_', 'uploads/organizations/org_') WHERE logo_path LIKE 'uploads/org_%'");
    }

    if (tableColumnExists($pdo, 'financial_transactions', 'receipt_path')) {
        $pdo->exec("UPDATE financial_transactions SET receipt_path = REPLACE(receipt_path, 'uploads/receipt_', 'uploads/receipts/receipt_') WHERE receipt_path LIKE 'uploads/receipt_%'");
    }
}

function ensureBudgetFlowSchema(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    addColumnIfNotExists(
        $pdo,
        'financial_transactions',
        'expense_request_id',
        'expense_request_id INT NULL',
        'expense_request_id INTEGER NULL'
    );
    addColumnIfNotExists(
        $pdo,
        'financial_transactions',
        'is_voided',
        'is_voided TINYINT(1) NOT NULL DEFAULT 0',
        'is_voided INTEGER NOT NULL DEFAULT 0'
    );
    addColumnIfNotExists(
        $pdo,
        'financial_transactions',
        'voided_at',
        'voided_at DATETIME NULL',
        'voided_at TEXT NULL'
    );
    addColumnIfNotExists(
        $pdo,
        'financial_transactions',
        'void_reason',
        'void_reason TEXT NULL',
        'void_reason TEXT NULL'
    );

    if ($driver === 'mysql') {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS budgets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                created_by INTEGER NULL,
                title TEXT NOT NULL,
                period_start TEXT NOT NULL,
                period_end TEXT NOT NULL,
                total_amount REAL NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','active','closed')),
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS budget_line_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                budget_id INTEGER NOT NULL,
                category_name TEXT NOT NULL,
                description TEXT NULL,
                allocated_amount REAL NOT NULL DEFAULT 0,
                spent_amount REAL NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS expense_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                budget_id INTEGER NOT NULL,
                budget_line_item_id INTEGER NOT NULL,
                requested_by INTEGER NULL,
                amount REAL NOT NULL,
                description TEXT NOT NULL,
                receipt_path TEXT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
                admin_note TEXT NULL,
                reviewed_by INTEGER NULL,
                reviewed_at TEXT NULL,
                transaction_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
                FOREIGN KEY (budget_line_item_id) REFERENCES budget_line_items(id) ON DELETE CASCADE,
                FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE SET NULL
            )
        ");
    }

    createIndexIfNotExists($pdo, 'financial_transactions', 'idx_tx_expense_request', 'expense_request_id');
    createIndexIfNotExists($pdo, 'budgets', 'idx_budgets_org_status', 'organization_id, status');
    createIndexIfNotExists($pdo, 'budgets', 'idx_budgets_period', 'period_start, period_end');
    createIndexIfNotExists($pdo, 'budget_line_items', 'idx_budget_lines_budget', 'budget_id');
    createIndexIfNotExists($pdo, 'expense_requests', 'idx_expense_requests_org_status', 'organization_id, status');
    createIndexIfNotExists($pdo, 'expense_requests', 'idx_expense_requests_line_status', 'budget_line_item_id, status');
    createIndexIfNotExists($pdo, 'expense_requests', 'idx_expense_requests_transaction', 'transaction_id');
}

function ensureProfileMediaColumns(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if (!tableColumnExists($pdo, 'users', 'profile_picture_path')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN profile_picture_path VARCHAR(255) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN profile_picture_path TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'profile_picture_crop_x')) {
        $pdo->exec($driver === 'mysql' ? 'ALTER TABLE users ADD COLUMN profile_picture_crop_x DECIMAL(5,2) NOT NULL DEFAULT 50.00' : 'ALTER TABLE users ADD COLUMN profile_picture_crop_x REAL NOT NULL DEFAULT 50');
    }

    if (!tableColumnExists($pdo, 'users', 'profile_picture_crop_y')) {
        $pdo->exec($driver === 'mysql' ? 'ALTER TABLE users ADD COLUMN profile_picture_crop_y DECIMAL(5,2) NOT NULL DEFAULT 50.00' : 'ALTER TABLE users ADD COLUMN profile_picture_crop_y REAL NOT NULL DEFAULT 50');
    }

    if (!tableColumnExists($pdo, 'users', 'profile_picture_zoom')) {
        $pdo->exec($driver === 'mysql' ? 'ALTER TABLE users ADD COLUMN profile_picture_zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00' : 'ALTER TABLE users ADD COLUMN profile_picture_zoom REAL NOT NULL DEFAULT 1');
    }

    if (!tableColumnExists($pdo, 'organizations', 'logo_path')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE organizations ADD COLUMN logo_path VARCHAR(255) NULL');
        } else {
            $pdo->exec('ALTER TABLE organizations ADD COLUMN logo_path TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'organizations', 'logo_crop_x')) {
        $pdo->exec($driver === 'mysql' ? 'ALTER TABLE organizations ADD COLUMN logo_crop_x DECIMAL(5,2) NOT NULL DEFAULT 50.00' : 'ALTER TABLE organizations ADD COLUMN logo_crop_x REAL NOT NULL DEFAULT 50');
    }

    if (!tableColumnExists($pdo, 'organizations', 'logo_crop_y')) {
        $pdo->exec($driver === 'mysql' ? 'ALTER TABLE organizations ADD COLUMN logo_crop_y DECIMAL(5,2) NOT NULL DEFAULT 50.00' : 'ALTER TABLE organizations ADD COLUMN logo_crop_y REAL NOT NULL DEFAULT 50');
    }

    if (!tableColumnExists($pdo, 'organizations', 'logo_zoom')) {
        $pdo->exec($driver === 'mysql' ? 'ALTER TABLE organizations ADD COLUMN logo_zoom DECIMAL(5,2) NOT NULL DEFAULT 1.00' : 'ALTER TABLE organizations ADD COLUMN logo_zoom REAL NOT NULL DEFAULT 1');
    }
}

function ensureAcademicAndVisibilityColumns(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if (!tableColumnExists($pdo, 'users', 'institute')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN institute VARCHAR(191) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN institute TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'program')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN program VARCHAR(191) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN program TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'year_level')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN year_level TINYINT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN year_level INTEGER NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'section')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN section VARCHAR(50) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN section TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'organizations', 'org_category')) {
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE organizations ADD COLUMN org_category VARCHAR(50) NOT NULL DEFAULT 'collegewide'");
        } else {
            $pdo->exec("ALTER TABLE organizations ADD COLUMN org_category TEXT NOT NULL DEFAULT 'collegewide'");
        }
    }

    if (!tableColumnExists($pdo, 'organizations', 'target_institute')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE organizations ADD COLUMN target_institute VARCHAR(191) NULL');
        } else {
            $pdo->exec('ALTER TABLE organizations ADD COLUMN target_institute TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'organizations', 'target_program')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE organizations ADD COLUMN target_program VARCHAR(191) NULL');
        } else {
            $pdo->exec('ALTER TABLE organizations ADD COLUMN target_program TEXT NULL');
        }
    }
}

function ensureAnnouncementPinColumns(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if (!tableColumnExists($pdo, 'announcements', 'is_pinned')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0');
        } else {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0');
        }
    }

    if (!tableColumnExists($pdo, 'announcements', 'pinned_at')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN pinned_at TEXT NULL');
        }
    }
}

function ensureAnnouncementLifecycleColumns(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if (!tableColumnExists($pdo, 'announcements', 'label')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN label VARCHAR(80) NULL');
        } else {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN label TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'announcements', 'duration_days')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 30');
        } else {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN duration_days INTEGER NOT NULL DEFAULT 30');
        }
    }

    if (!tableColumnExists($pdo, 'announcements', 'expires_at')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN expires_at DATETIME NULL');
        } else {
            $pdo->exec('ALTER TABLE announcements ADD COLUMN expires_at TEXT NULL');
        }
    }

    if ($driver === 'mysql') {
        $pdo->exec('UPDATE announcements SET duration_days = 30 WHERE duration_days IS NULL OR duration_days <= 0');
        $pdo->exec('UPDATE announcements SET expires_at = DATE_ADD(created_at, INTERVAL duration_days DAY) WHERE expires_at IS NULL');
        return;
    }

    $pdo->exec('UPDATE announcements SET duration_days = 30 WHERE duration_days IS NULL OR duration_days <= 0');
    $pdo->exec("UPDATE announcements SET expires_at = datetime(created_at, '+' || duration_days || ' days') WHERE expires_at IS NULL");
}

function tableColumnExists(PDO $pdo, string $table, string $column): bool
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
    $stmt->execute();
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        if ((string) ($col['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function addColumnIfNotExists(PDO $pdo, string $table, string $column, string $mysqlDefinition, string $sqliteDefinition): void
{
    if (tableColumnExists($pdo, $table, $column)) {
        return;
    }

    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $definition = $driver === 'mysql' ? $mysqlDefinition : $sqliteDefinition;
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $definition);
}

function createIndexIfNotExists(PDO $pdo, string $table, string $indexName, string $columns): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, $indexName]);
        if (((int) $stmt->fetchColumn()) > 0) {
            return;
        }
    } else {
        $stmt = $pdo->prepare('PRAGMA index_list(' . $table . ')');
        $stmt->execute();
        foreach ($stmt->fetchAll() as $index) {
            if ((string) ($index['name'] ?? '') === $indexName) {
                return;
            }
        }
    }

    $pdo->exec('CREATE INDEX ' . $indexName . ' ON ' . $table . ' (' . $columns . ')');
}

function createIndexIfColumnsExist(PDO $pdo, string $table, string $indexName, array $columns): void
{
    foreach ($columns as $column) {
        if (!tableColumnExists($pdo, $table, (string) $column)) {
            return;
        }
    }

    createIndexIfNotExists($pdo, $table, $indexName, implode(', ', $columns));
}

function ensurePerformanceIndexes(PDO $pdo): void
{
    createIndexIfColumnsExist($pdo, 'financial_transactions', 'idx_tx_org_date_id', ['organization_id', 'transaction_date', 'id']);
    createIndexIfColumnsExist($pdo, 'financial_transactions', 'idx_tx_date_created', ['transaction_date', 'created_at']);
    createIndexIfColumnsExist($pdo, 'financial_transactions', 'idx_tx_type_date', ['type', 'transaction_date']);

    createIndexIfColumnsExist($pdo, 'announcements', 'idx_ann_org_exp_created', ['organization_id', 'expires_at', 'created_at']);
    createIndexIfColumnsExist($pdo, 'announcements', 'idx_ann_pinned_created', ['is_pinned', 'pinned_at', 'created_at']);

    createIndexIfColumnsExist($pdo, 'organization_members', 'idx_org_members_user_org', ['user_id', 'organization_id']);
    createIndexIfColumnsExist($pdo, 'organization_members', 'idx_org_members_org_joined', ['organization_id', 'joined_at']);

    createIndexIfColumnsExist($pdo, 'organization_join_requests', 'idx_join_user_status_updated', ['user_id', 'status', 'updated_at']);
    createIndexIfColumnsExist($pdo, 'organization_join_requests', 'idx_join_org_status_created', ['organization_id', 'status', 'created_at']);

    createIndexIfColumnsExist($pdo, 'owner_assignments', 'idx_owner_student_status_created', ['student_id', 'status', 'created_at']);
    createIndexIfColumnsExist($pdo, 'owner_assignments', 'idx_owner_org_status', ['organization_id', 'status']);

    createIndexIfColumnsExist($pdo, 'transaction_change_requests', 'idx_txcr_org_status_created', ['organization_id', 'status', 'created_at']);
    createIndexIfColumnsExist($pdo, 'transaction_change_requests', 'idx_txcr_requested_status_updated', ['requested_by', 'status', 'updated_at']);
    createIndexIfColumnsExist($pdo, 'transaction_change_requests', 'idx_txcr_tx_action_status', ['transaction_id', 'action_type', 'status']);

    createIndexIfColumnsExist($pdo, 'audit_logs', 'idx_audit_user_created', ['user_id', 'created_at']);
    createIndexIfColumnsExist($pdo, 'audit_logs', 'idx_audit_created_id', ['created_at', 'id']);
    createIndexIfColumnsExist($pdo, 'audit_logs', 'idx_audit_entity', ['entity_type', 'entity_id']);

    createIndexIfColumnsExist($pdo, 'security_notifications', 'idx_security_user_created', ['user_id', 'created_at']);
    createIndexIfColumnsExist($pdo, 'security_notifications', 'idx_security_user_event_created', ['user_id', 'event_type', 'created_at']);
}

function ensureAuthEnhancementColumns(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    addColumnIfNotExists($pdo, 'users', 'onboarding_done', 'onboarding_done TINYINT(1) NOT NULL DEFAULT 0', 'onboarding_done INTEGER NOT NULL DEFAULT 0');

    if (!tableColumnExists($pdo, 'users', 'email_verified')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 1');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'email_verified_at')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'activation_token')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN activation_token VARCHAR(64) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN activation_token TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'activation_expires')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN activation_expires TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN activation_expires TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'reset_token')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_token TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'reset_expires')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_expires TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_expires TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'account_status')) {
        if ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE users ADD COLUMN account_status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active'");
        } else {
            $pdo->exec("ALTER TABLE users ADD COLUMN account_status TEXT NOT NULL DEFAULT 'active'");
        }
    }

    if (!tableColumnExists($pdo, 'users', 'last_login_at')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'last_login_ip')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_ip VARCHAR(45) NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_ip TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'password_changed_at')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN password_changed_at TEXT NULL');
        }
    }

    if (!tableColumnExists($pdo, 'users', 'password_reset_at')) {
        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE users ADD COLUMN password_reset_at TIMESTAMP NULL DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN password_reset_at TEXT NULL');
        }
    }
}

function ensureAuthEnhancementTables(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'mysql') {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                email VARCHAR(191) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                attempt_type ENUM('success','failed') NOT NULL,
                failure_reason VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_login_history_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
                INDEX idx_login_history_user (user_id),
                INDEX idx_login_history_email (email),
                INDEX idx_login_history_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_password_history_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX idx_password_history_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS security_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                event_data TEXT NULL,
                sent_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_security_notif_user FOREIGN KEY (user_id)
                    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX idx_security_notif_user (user_id),
                INDEX idx_security_notif_sent (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_token TEXT NOT NULL UNIQUE,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                device_fingerprint TEXT NULL,
                last_activity TEXT NOT NULL DEFAULT (datetime('now')),
                expires_at TEXT NOT NULL,
                is_remembered INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                email TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                attempt_type TEXT NOT NULL CHECK(attempt_type IN ('success','failed')),
                failure_reason TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS security_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                event_data TEXT NULL,
                sent_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }
}

function ensureDefaultAdmin(PDO $pdo): void
{

    $exists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($exists === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, institute, program, year_level, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'System Admin',
            'admin@campus.local',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            null,
            null,
            null,
            null,
        ]);
    }
}
