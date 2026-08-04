<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('equipment.php'));
}
verify_csrf();
$pdo = db();

$id = (int) ($_POST['id'] ?? 0);
$assetTag = strtoupper(trim((string) ($_POST['asset_tag'] ?? '')));
$equipmentType = trim((string) ($_POST['equipment_type'] ?? ''));
$brand = trim((string) ($_POST['brand'] ?? ''));
$model = trim((string) ($_POST['model'] ?? ''));
$serialNumber = trim((string) ($_POST['serial_number'] ?? ''));
$assignedTo = trim((string) ($_POST['assigned_to'] ?? ''));
$department = trim((string) ($_POST['department'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'In Use'));
$purchaseDate = trim((string) ($_POST['purchase_date'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($assetTag === '' || !is_valid_option($equipmentType, EQUIPMENT_TYPES) || !is_valid_option($status, EQUIPMENT_STATUSES)) {
    flash('danger', 'Asset tag, equipment type, and a valid status are required.');
    redirect(url('equipment_form.php' . ($id ? '?id=' . $id : '')));
}

$duplicate = $pdo->prepare('SELECT id FROM equipment WHERE asset_tag = ? AND id <> ?');
$duplicate->execute([$assetTag, $id]);
if ($duplicate->fetchColumn()) {
    flash('danger', 'Asset tag ' . $assetTag . ' is already registered.');
    redirect(url('equipment_form.php' . ($id ? '?id=' . $id : '')));
}

$values = [
    ':asset_tag' => $assetTag,
    ':equipment_type' => $equipmentType,
    ':brand' => $brand ?: null,
    ':model' => $model ?: null,
    ':serial_number' => $serialNumber ?: null,
    ':assigned_to' => $assignedTo ?: null,
    ':department' => $department ?: null,
    ':location' => $location ?: null,
    ':status' => $status,
    ':purchase_date' => $purchaseDate ?: null,
    ':notes' => $notes ?: null,
    ':user_id' => user_id(),
    ':user_name' => user_name(),
];

if ($id > 0) {
    $values[':id'] = $id;
    $stmt = $pdo->prepare(
        "UPDATE equipment SET
            asset_tag = :asset_tag, equipment_type = :equipment_type, brand = :brand, model = :model,
            serial_number = :serial_number, assigned_to = :assigned_to, department = :department,
            location = :location, status = :status, purchase_date = :purchase_date, notes = :notes,
            updated_by = :user_id, updated_by_name = :user_name
         WHERE id = :id"
    );
    $stmt->execute($values);
    flash('success', $assetTag . ' was updated successfully.');
} else {
    $insertValues = $values;
    unset($insertValues[':user_id'], $insertValues[':user_name']);
    $insertValues[':created_by'] = user_id();
    $insertValues[':created_by_name'] = user_name();
    $insertValues[':updated_by'] = user_id();
    $insertValues[':updated_by_name'] = user_name();
    $stmt = $pdo->prepare(
        "INSERT INTO equipment
         (asset_tag, equipment_type, brand, model, serial_number, assigned_to, department, location,
          status, purchase_date, notes, created_by, created_by_name, updated_by, updated_by_name)
         VALUES
         (:asset_tag, :equipment_type, :brand, :model, :serial_number, :assigned_to, :department, :location,
          :status, :purchase_date, :notes, :created_by, :created_by_name, :updated_by, :updated_by_name)"
    );
    $stmt->execute($insertValues);
    $id = (int) $pdo->lastInsertId();
    flash('success', $assetTag . ' was registered successfully.');
}

redirect(url('equipment_form.php?id=' . $id));
