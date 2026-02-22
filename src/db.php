<?php

declare(strict_types=1);

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
        $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
        $port = (int) ($dbConfig['port'] ?? 3306);
        $database = (string) ($dbConfig['database'] ?? 'websys_db');
        $username = (string) ($dbConfig['username'] ?? 'root');
        $password = (string) ($dbConfig['password'] ?? '');

        $bootstrapDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
        $bootstrapPdo = new PDO($bootstrapDsn, $username, $password);
        $bootstrapPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

function initializeDatabaseSqlite(PDO $pdo): void
{
    $schema = <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'student' CHECK(role IN ('admin','student','owner')),
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS organizations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT DEFAULT '',
        owner_id INTEGER NULL,
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
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
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
    SQL;

    $pdo->exec($schema);
    ensureDefaultAdmin($pdo);
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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS organizations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL UNIQUE,
        description TEXT,
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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_tx_org FOREIGN KEY (organization_id)
            REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE
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
    SQL;

    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensureDefaultAdmin($pdo);
}

function ensureDefaultAdmin(PDO $pdo): void
{

    $exists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($exists === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            'System Admin',
            'admin@campus.local',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
        ]);
    }
}
