CREATE DATABASE IF NOT EXISTS equipment_repair_monitoring
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE equipment_repair_monitoring;

CREATE TABLE IF NOT EXISTS equipment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_tag VARCHAR(60) NOT NULL UNIQUE,
    equipment_type VARCHAR(80) NOT NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(120) NULL,
    serial_number VARCHAR(120) NULL,
    assigned_to VARCHAR(150) NULL,
    department VARCHAR(120) NULL,
    location VARCHAR(150) NULL,
    status ENUM('In Service','Under Repair','For Replacement','Retired') NOT NULL DEFAULT 'In Service',
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_requests (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_history (
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
) ENGINE=InnoDB;
