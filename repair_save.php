<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('repairs.php'));
}
verify_csrf();
$pdo = db();

$id = (int) ($_POST['id'] ?? 0);
$equipmentId = (int) ($_POST['equipment_id'] ?? 0) ?: null;
$equipmentDescription = trim((string) ($_POST['equipment_description'] ?? ''));
$reportedBy = trim((string) ($_POST['reported_by'] ?? ''));
$department = trim((string) ($_POST['department'] ?? ''));
$contactDetails = trim((string) ($_POST['contact_details'] ?? ''));
$issueCategory = trim((string) ($_POST['issue_category'] ?? ''));
$problemDescription = trim((string) ($_POST['problem_description'] ?? ''));
$priority = trim((string) ($_POST['priority'] ?? 'Normal'));
$dateReportedInput = trim((string) ($_POST['date_reported'] ?? ''));
$dateReportedTime = strtotime($dateReportedInput);

$errors = [];
if ($equipmentDescription === '') $errors[] = 'Equipment description is required.';
if ($reportedBy === '') $errors[] = 'Requester name is required.';
if ($department === '') $errors[] = 'Department or branch is required.';
if ($problemDescription === '') $errors[] = 'Problem description is required.';
if (!is_valid_option($issueCategory, ISSUE_CATEGORIES)) $errors[] = 'Choose a valid issue category.';
if (!is_valid_option($priority, REPAIR_PRIORITIES)) $errors[] = 'Choose a valid priority.';
if (!$dateReportedTime) $errors[] = 'Choose a valid reported date and time.';

if ($equipmentId) {
    $check = $pdo->prepare('SELECT id FROM equipment WHERE id = ?');
    $check->execute([$equipmentId]);
    if (!$check->fetchColumn()) $errors[] = 'The selected equipment record no longer exists.';
}

if ($errors) {
    flash('danger', implode(' ', $errors));
    redirect(url('repair_form.php' . ($id ? '?id=' . $id : '')));
}

try {
    $pdo->beginTransaction();

    if ($id === 0) {
        $ticketNo = next_ticket_number($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO repair_requests
             (ticket_no, equipment_id, equipment_description, reported_by, department, contact_details,
              issue_category, problem_description, priority, status, date_reported, received_at,
              created_by, created_by_name, updated_by, updated_by_name)
             VALUES
             (:ticket_no, :equipment_id, :equipment_description, :reported_by, :department, :contact_details,
              :issue_category, :problem_description, :priority, 'Submitted', :date_reported, NOW(),
              :created_by, :created_by_name, :updated_by, :updated_by_name)"
        );
        $stmt->execute([
            ':ticket_no' => $ticketNo,
            ':equipment_id' => $equipmentId,
            ':equipment_description' => $equipmentDescription,
            ':reported_by' => $reportedBy,
            ':department' => $department,
            ':contact_details' => $contactDetails ?: null,
            ':issue_category' => $issueCategory,
            ':problem_description' => $problemDescription,
            ':priority' => $priority,
            ':date_reported' => date('Y-m-d H:i:s', $dateReportedTime),
            ':created_by' => user_id(),
            ':created_by_name' => user_name(),
            ':updated_by' => user_id(),
            ':updated_by_name' => user_name(),
        ]);
        $id = (int) $pdo->lastInsertId();
        repair_history($pdo, $id, 'REQUEST_CREATED', $ticketNo . ' was created.', null, 'Submitted', 'Issue reported by ' . $reportedBy . '.');
        $pdo->commit();
        flash('success', $ticketNo . ' was created successfully.');
        redirect(url('repair_view.php?id=' . $id));
    }

    $old = fetch_repair($pdo, $id);
    if (!$old) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Repair request not found.');
    }

    $status = trim((string) ($_POST['status'] ?? $old['status']));
    if (!is_valid_option($status, REPAIR_STATUSES)) {
        $status = $old['status'];
    }
    $assignedTechnician = trim((string) ($_POST['assigned_technician'] ?? ''));
    $targetCompletion = trim((string) ($_POST['target_completion_date'] ?? ''));
    $diagnosis = trim((string) ($_POST['diagnosis'] ?? ''));
    $actionTaken = trim((string) ($_POST['action_taken'] ?? ''));
    $partsUsed = trim((string) ($_POST['parts_used'] ?? ''));
    $repairCost = max(0, (float) ($_POST['repair_cost'] ?? 0));
    $releasedTo = trim((string) ($_POST['released_to'] ?? ''));
    $releasedInput = trim((string) ($_POST['released_at'] ?? ''));
    $releasedTime = $releasedInput !== '' ? strtotime($releasedInput) : false;
    $statusNote = trim((string) ($_POST['status_note'] ?? ''));
    $completedAt = $status === 'Completed'
        ? ($old['completed_at'] ?: date('Y-m-d H:i:s'))
        : null;

    $stmt = $pdo->prepare(
        "UPDATE repair_requests SET
            equipment_id = :equipment_id,
            equipment_description = :equipment_description,
            reported_by = :reported_by,
            department = :department,
            contact_details = :contact_details,
            issue_category = :issue_category,
            problem_description = :problem_description,
            priority = :priority,
            status = :status,
            assigned_technician = :assigned_technician,
            date_reported = :date_reported,
            target_completion_date = :target_completion_date,
            diagnosis = :diagnosis,
            action_taken = :action_taken,
            parts_used = :parts_used,
            repair_cost = :repair_cost,
            completed_at = :completed_at,
            released_to = :released_to,
            released_at = :released_at,
            updated_by = :updated_by,
            updated_by_name = :updated_by_name
         WHERE id = :id"
    );
    $stmt->execute([
        ':equipment_id' => $equipmentId,
        ':equipment_description' => $equipmentDescription,
        ':reported_by' => $reportedBy,
        ':department' => $department,
        ':contact_details' => $contactDetails ?: null,
        ':issue_category' => $issueCategory,
        ':problem_description' => $problemDescription,
        ':priority' => $priority,
        ':status' => $status,
        ':assigned_technician' => $assignedTechnician ?: null,
        ':date_reported' => date('Y-m-d H:i:s', $dateReportedTime),
        ':target_completion_date' => $targetCompletion ?: null,
        ':diagnosis' => $diagnosis ?: null,
        ':action_taken' => $actionTaken ?: null,
        ':parts_used' => $partsUsed ?: null,
        ':repair_cost' => $repairCost,
        ':completed_at' => $completedAt,
        ':released_to' => $releasedTo ?: null,
        ':released_at' => $releasedTime ? date('Y-m-d H:i:s', $releasedTime) : null,
        ':updated_by' => user_id(),
        ':updated_by_name' => user_name(),
        ':id' => $id,
    ]);

    if ($status !== $old['status']) {
        repair_history(
            $pdo,
            $id,
            'STATUS_CHANGED',
            $old['ticket_no'] . ' moved from ' . $old['status'] . ' to ' . $status . '.',
            $old['status'],
            $status,
            $statusNote
        );
    } else {
        repair_history($pdo, $id, 'REQUEST_UPDATED', $old['ticket_no'] . ' details were updated.', $status, $status, $statusNote);
    }

    $pdo->commit();
    flash('success', $old['ticket_no'] . ' was updated successfully.');
    redirect(url('repair_view.php?id=' . $id));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('danger', 'The repair request could not be saved. Please review the form and try again.');
    redirect(url('repair_form.php' . ($id ? '?id=' . $id : '')));
}
