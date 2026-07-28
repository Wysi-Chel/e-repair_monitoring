<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(h.action_description LIKE :q_description OR h.user_name LIKE :q_user OR h.notes LIKE :q_notes OR r.ticket_no LIKE :q_ticket)';
    foreach ([':q_description', ':q_user', ':q_notes', ':q_ticket'] as $placeholder) {
        $params[$placeholder] = '%' . $q . '%';
    }
}
if ($action !== '') {
    $where[] = 'h.action_type = :action';
    $params[':action'] = $action;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(h.created_at) >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(h.created_at) <= :date_to';
    $params[':date_to'] = $dateTo;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare(
    "SELECT h.*, r.ticket_no, r.reported_by, r.department
     FROM repair_history h
     JOIN repair_requests r ON r.id = h.repair_request_id
     $whereSql
     ORDER BY h.created_at DESC, h.id DESC
     LIMIT 300"
);
$stmt->execute($params);
$activities = $stmt->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action_type FROM repair_history ORDER BY action_type')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Activity Log';
$pageSubtitle = 'Review the audit trail for repair requests and status updates.';
require __DIR__ . '/includes/header.php';
?>
<section class="card filter-card">
    <form class="filters activity-filters" method="get">
        <div class="form-group search-field">
            <label for="q">Search activity</label>
            <div class="input-with-icon">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="q" name="q" value="<?= e($q) ?>" placeholder="Ticket, action, note, or user">
            </div>
        </div>
        <div class="form-group">
            <label for="action">Action</label>
            <select id="action" name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $option): ?><option value="<?= e($option) ?>" <?= $action === $option ? 'selected' : '' ?>><?= e(ucwords(strtolower(str_replace('_', ' ', $option)))) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="date_from">From</label><input id="date_from" type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div class="form-group"><label for="date_to">To</label><input id="date_to" type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-secondary" href="<?= url('activity.php') ?>">Clear</a></div>
    </form>
</section>

<section class="card">
    <div class="card-header"><div><span class="section-kicker">Audit trail</span><h2><?= count($activities) ?> recent event<?= count($activities) === 1 ? '' : 's' ?></h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date and time</th><th>Ticket</th><th>Action</th><th>Activity</th><th>User</th><th>IP address</th></tr></thead>
            <tbody>
            <?php foreach ($activities as $item): ?>
                <tr>
                    <td><?= e(display_datetime($item['created_at'])) ?></td>
                    <td><a class="ticket-link" href="<?= url('repair_view.php?id=' . (int) $item['repair_request_id']) ?>"><?= e($item['ticket_no']) ?></a><small><?= e($item['department']) ?></small></td>
                    <td><span class="activity-type"><?= e(ucwords(strtolower(str_replace('_', ' ', $item['action_type'])))) ?></span></td>
                    <td><strong><?= e($item['action_description']) ?></strong><?php if ($item['notes']): ?><small class="truncate"><?= e($item['notes']) ?></small><?php endif; ?></td>
                    <td><?= e($item['user_name']) ?></td>
                    <td><?= e($item['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$activities): ?><tr><td colspan="6" class="empty-state"><strong>No matching activity</strong><span>Activity appears here when repair requests are created or updated.</span></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
