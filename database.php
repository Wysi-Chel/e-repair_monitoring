<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $server = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
            DB_USER,
            DB_PASS,
            pdo_options()
        );
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
            DB_USER,
            DB_PASS,
            pdo_options()
        );
        ensure_schema($pdo);
        return $pdo;
    } catch (PDOException $exception) {
        http_response_code(500);
        exit(
            '<h1>Database connection failed</h1>' .
            '<p>Start MySQL in XAMPP, then reload this page.</p>'
        );
    }
}

function pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

function ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS equipment (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asset_tag VARCHAR(60) NOT NULL UNIQUE,
            equipment_type VARCHAR(80) NOT NULL,
            brand VARCHAR(100) NULL,
            model VARCHAR(120) NULL,
            serial_number VARCHAR(120) NULL,
            assigned_to VARCHAR(150) NULL,
            department VARCHAR(120) NULL,
            location VARCHAR(150) NULL,
            status ENUM('In Use','Not in Use','Retired') NOT NULL DEFAULT 'In Use',
            purchase_date DATE NULL,
            notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_by_name VARCHAR(150) NULL,
            updated_by INT UNSIGNED NULL,
            updated_by_name VARCHAR(150) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_equipment_status (status),
            INDEX idx_equipment_type (equipment_type),
            INDEX idx_equipment_department (department)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS repair_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_no VARCHAR(30) NOT NULL UNIQUE,
            equipment_id INT UNSIGNED NULL,
            equipment_description VARCHAR(255) NOT NULL,
            reported_by VARCHAR(150) NOT NULL,
            department VARCHAR(120) NOT NULL,
            contact_details VARCHAR(150) NULL,
            issue_category VARCHAR(80) NOT NULL,
            problem_description TEXT NOT NULL,
            priority ENUM('Low','Normal','High','Critical') NOT NULL DEFAULT 'Normal',
            status ENUM('Submitted','Diagnosing','In Repair','Awaiting Parts','Ready for Release','Completed','Cancelled') NOT NULL DEFAULT 'Submitted',
            assigned_technician VARCHAR(150) NULL,
            date_reported DATETIME NOT NULL,
            received_at DATETIME NULL,
            target_completion_date DATE NULL,
            diagnosis TEXT NULL,
            action_taken TEXT NULL,
            parts_used TEXT NULL,
            repair_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            completed_at DATETIME NULL,
            released_to VARCHAR(150) NULL,
            released_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_by_name VARCHAR(150) NULL,
            updated_by INT UNSIGNED NULL,
            updated_by_name VARCHAR(150) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_repair_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL,
            INDEX idx_repair_status (status),
            INDEX idx_repair_priority (priority),
            INDEX idx_repair_reported (date_reported),
            INDEX idx_repair_technician (assigned_technician),
            INDEX idx_repair_department (department)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS repair_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            repair_request_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            user_name VARCHAR(150) NOT NULL,
            action_type VARCHAR(60) NOT NULL,
            action_description VARCHAR(500) NOT NULL,
            status_from VARCHAR(40) NULL,
            status_to VARCHAR(40) NULL,
            notes TEXT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_history_repair FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id) ON DELETE CASCADE,
            INDEX idx_history_repair (repair_request_id),
            INDEX idx_history_created (created_at),
            INDEX idx_history_action (action_type)
        ) ENGINE=InnoDB",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $equipmentStatusColumn = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'status'")->fetch();
    $equipmentStatusType = (string) ($equipmentStatusColumn['Type'] ?? '');
    if ($equipmentStatusColumn && (
        !str_contains($equipmentStatusType, "'In Use'")
        || str_contains($equipmentStatusType, "'In Service'")
    )) {
        $pdo->exec(
            "ALTER TABLE equipment
             MODIFY status ENUM('In Service','Under Repair','Completed','For Replacement','Retired','In Use','Not in Use')
             NOT NULL DEFAULT 'In Use'"
        );
        $pdo->exec(
            "UPDATE equipment
             SET status = CASE
                 WHEN status = 'Retired' THEN 'Retired'
                 WHEN status = 'In Service' THEN 'In Use'
                 ELSE 'Not in Use'
             END"
        );
        $pdo->exec(
            "ALTER TABLE equipment
             MODIFY status ENUM('In Use','Not in Use','Retired')
             NOT NULL DEFAULT 'In Use'"
        );
    }
    $ready = true;
}
