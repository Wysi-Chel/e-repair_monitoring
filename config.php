<?php
declare(strict_types=1);

const APP_NAME = 'Equipment Repair Monitoring';
const APP_SHORT_NAME = 'Repair Monitoring';
const APP_BASE = '/e-repair_system';
const PORTAL_BASE = '/micei_mis';

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'equipment_repair_monitoring';
const DB_USER = 'root';
const DB_PASS = '';

const REPAIR_STATUSES = [
    'Submitted',
    'Diagnosing',
    'In Repair',
    'Awaiting Parts',
    'Ready for Release',
    'Completed',
    'Cancelled',
];

const REPAIR_PRIORITIES = ['Low', 'Normal', 'High', 'Critical'];
const ISSUE_CATEGORIES = ['Hardware', 'Software', 'Network', 'Peripheral', 'Preventive Maintenance', 'Other'];
const EQUIPMENT_STATUSES = ['In Service', 'Under Repair', 'For Replacement', 'Retired'];
const EQUIPMENT_TYPES = [
    'Desktop Computer',
    'Laptop',
    'Monitor',
    'Printer',
    'Scanner',
    'UPS',
    'Network Device',
    'Mobile Device',
    'Other',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');
