<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function require_portal_auth(): void
{
    if (!current_user()) {
        $_SESSION['repair_return_to'] = url();
        redirect(PORTAL_BASE . '/login.php');
    }
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['repair_csrf_token'])) {
        $_SESSION['repair_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['repair_csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please return to the previous page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['repair_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['repair_flash'] ?? [];
    unset($_SESSION['repair_flash']);
    return is_array($messages) ? $messages : [];
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function user_id(): ?int
{
    $id = current_user()['id'] ?? null;
    return is_numeric($id) ? (int) $id : null;
}

function user_name(): string
{
    return trim((string) (current_user()['full_name'] ?? 'Portal user')) ?: 'Portal user';
}

function is_valid_option(string $value, array $options): bool
{
    return in_array($value, $options, true);
}

function status_class(string $status): string
{
    return strtolower(str_replace([' ', '/'], ['-', '-'], $status));
}

function display_date(?string $value, string $fallback = '—'): string
{
    if (!$value) {
        return $fallback;
    }
    $time = strtotime($value);
    return $time ? date('M d, Y', $time) : $fallback;
}

function display_datetime(?string $value, string $fallback = '—'): string
{
    if (!$value) {
        return $fallback;
    }
    $time = strtotime($value);
    return $time ? date('M d, Y · h:i A', $time) : $fallback;
}

function input_datetime(?string $value): string
{
    if (!$value) {
        return date('Y-m-d\TH:i');
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d\TH:i', $time) : '';
}

function money(mixed $value): string
{
    return '₱' . number_format((float) $value, 2);
}

function next_ticket_number(PDO $pdo): string
{
    $prefix = 'ER-' . date('Y') . '-';
    $stmt = $pdo->prepare(
        'SELECT ticket_no FROM repair_requests WHERE ticket_no LIKE ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$prefix . '%']);
    $last = (string) ($stmt->fetchColumn() ?: '');
    $sequence = $last !== '' ? (int) substr($last, -4) + 1 : 1;
    return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
}

function equipment_label(array $equipment): string
{
    $detail = trim(implode(' ', array_filter([
        $equipment['brand'] ?? '',
        $equipment['model'] ?? '',
    ])));
    $label = (string) ($equipment['asset_tag'] ?? 'No asset tag') . ' · ' . (string) ($equipment['equipment_type'] ?? 'Equipment');
    return $detail !== '' ? $label . ' · ' . $detail : $label;
}

function repair_history(
    PDO $pdo,
    int $repairId,
    string $actionType,
    string $description,
    ?string $statusFrom = null,
    ?string $statusTo = null,
    ?string $notes = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO repair_history
         (repair_request_id, user_id, user_name, action_type, action_description, status_from, status_to, notes, ip_address)
         VALUES (:repair_id, :user_id, :user_name, :action_type, :description, :status_from, :status_to, :notes, :ip)'
    );
    $stmt->execute([
        ':repair_id' => $repairId,
        ':user_id' => user_id(),
        ':user_name' => user_name(),
        ':action_type' => $actionType,
        ':description' => $description,
        ':status_from' => $statusFrom,
        ':status_to' => $statusTo,
        ':notes' => $notes !== '' ? $notes : null,
        ':ip' => client_ip(),
    ]);
}

function sync_equipment_repair_status(PDO $pdo, ?int $equipmentId, string $repairStatus): void
{
    if (!$equipmentId) {
        return;
    }

    if (in_array($repairStatus, ['Completed', 'Cancelled'], true)) {
        $open = $pdo->prepare(
            "SELECT COUNT(*) FROM repair_requests
             WHERE equipment_id = ? AND status NOT IN ('Completed','Cancelled')"
        );
        $open->execute([$equipmentId]);
        if ((int) $open->fetchColumn() === 0) {
            $equipmentStatus = $repairStatus === 'Completed' ? 'Completed' : 'In Service';
            $pdo->prepare(
                "UPDATE equipment
                 SET status = ?, updated_by = ?, updated_by_name = ?
                 WHERE id = ? AND status NOT IN ('For Replacement','Retired')"
            )->execute([$equipmentStatus, user_id(), user_name(), $equipmentId]);
        }
        return;
    }

    $pdo->prepare(
        "UPDATE equipment SET status = 'Under Repair', updated_by = ?, updated_by_name = ? WHERE id = ? AND status NOT IN ('For Replacement','Retired')"
    )->execute([user_id(), user_name(), $equipmentId]);
}

function fetch_repair(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT r.*, e.asset_tag, e.equipment_type, e.brand, e.model, e.serial_number,
                e.assigned_to, e.location, e.status AS equipment_status
         FROM repair_requests r
         LEFT JOIN equipment e ON e.id = r.equipment_id
         WHERE r.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
