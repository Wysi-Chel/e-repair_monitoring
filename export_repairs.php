<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$technician = trim((string) ($_GET['technician'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(r.ticket_no LIKE :q_ticket OR r.reported_by LIKE :q_reporter OR r.department LIKE :q_department OR
                r.problem_description LIKE :q_problem OR r.equipment_description LIKE :q_equipment OR e.asset_tag LIKE :q_asset)";
    foreach ([':q_ticket', ':q_reporter', ':q_department', ':q_problem', ':q_equipment', ':q_asset'] as $placeholder) {
        $params[$placeholder] = '%' . $q . '%';
    }
}
if (is_valid_option($status, REPAIR_STATUSES)) {
    $where[] = 'r.status = :status';
    $params[':status'] = $status;
}
if (is_valid_option($priority, REPAIR_PRIORITIES)) {
    $where[] = 'r.priority = :priority';
    $params[':priority'] = $priority;
}
if ($technician !== '') {
    $where[] = 'r.assigned_technician = :technician';
    $params[':technician'] = $technician;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(r.date_reported) >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(r.date_reported) <= :date_to';
    $params[':date_to'] = $dateTo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare(
    "SELECT r.*, e.asset_tag, e.equipment_type, e.brand, e.model, e.serial_number
     FROM repair_requests r
     LEFT JOIN equipment e ON e.id = r.equipment_id
     $whereSql
     ORDER BY r.date_reported DESC"
);
$stmt->execute($params);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="equipment-repairs-' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'Ticket No.', 'Date Reported', 'Asset Tag', 'Equipment', 'Serial No.', 'Reported By',
    'Department', 'Category', 'Problem', 'Priority', 'Status', 'Technician', 'Diagnosis',
    'Action Taken', 'Parts Used', 'Repair Cost', 'Target Completion', 'Completed At',
    'Released To', 'Released At'
]);
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['ticket_no'],
        $row['date_reported'],
        $row['asset_tag'],
        trim(implode(' ', array_filter([$row['equipment_type'], $row['brand'], $row['model']]))) ?: $row['equipment_description'],
        $row['serial_number'],
        $row['reported_by'],
        $row['department'],
        $row['issue_category'],
        $row['problem_description'],
        $row['priority'],
        $row['status'],
        $row['assigned_technician'],
        $row['diagnosis'],
        $row['action_taken'],
        $row['parts_used'],
        $row['repair_cost'],
        $row['target_completion_date'],
        $row['completed_at'],
        $row['released_to'],
        $row['released_at'],
    ]);
}
fclose($output);
exit;
