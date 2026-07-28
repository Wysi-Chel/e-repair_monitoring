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
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(r.ticket_no LIKE :q_ticket OR r.reported_by LIKE :q_reporter OR r.department LIKE :q_department OR
                r.problem_description LIKE :q_problem OR r.equipment_description LIKE :q_equipment OR
                e.asset_tag LIKE :q_asset OR e.serial_number LIKE :q_serial)";
    foreach ([':q_ticket', ':q_reporter', ':q_department', ':q_problem', ':q_equipment', ':q_asset', ':q_serial'] as $placeholder) {
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
$fromSql = "FROM repair_requests r LEFT JOIN equipment e ON e.id = r.equipment_id $whereSql";

$countStmt = $pdo->prepare("SELECT COUNT(*) $fromSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT r.*, e.asset_tag, e.brand, e.model
     $fromSql
     ORDER BY
        FIELD(r.priority, 'Critical','High','Normal','Low'),
        FIELD(r.status, 'Submitted','Diagnosing','In Repair','Awaiting Parts','Ready for Release','Completed','Cancelled'),
        r.date_reported DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$repairs = $stmt->fetchAll();

$technicians = $pdo->query(
    "SELECT DISTINCT assigned_technician FROM repair_requests
     WHERE assigned_technician IS NOT NULL AND assigned_technician <> ''
     ORDER BY assigned_technician"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Repair Requests';
$pageSubtitle = 'Track reported issues from intake through repair and release.';
require __DIR__ . '/includes/header.php';
?>
<section class="card filter-card">
    <form class="filters repair-filters" method="get">
        <div class="form-group search-field">
            <label for="q">Search requests</label>
            <div class="input-with-icon">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="q" name="q" value="<?= e($q) ?>" placeholder="Ticket, asset tag, requester, or issue">
            </div>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (REPAIR_STATUSES as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="priority">Priority</label>
            <select id="priority" name="priority">
                <option value="">All priorities</option>
                <?php foreach (REPAIR_PRIORITIES as $option): ?><option value="<?= e($option) ?>" <?= $priority === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="technician">Technician</label>
            <select id="technician" name="technician">
                <option value="">All technicians</option>
                <?php foreach ($technicians as $option): ?><option value="<?= e($option) ?>" <?= $technician === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="date_from">From</label>
            <input id="date_from" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label for="date_to">To</label>
            <input id="date_to" type="date" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-secondary" href="<?= url('repairs.php') ?>">Clear</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div><span class="section-kicker">Repair queue</span><h2><?= number_format($total) ?> request<?= $total === 1 ? '' : 's' ?></h2></div>
        <a class="btn btn-secondary btn-sm" href="<?= url('export_repairs.php?' . http_build_query($_GET)) ?>">
            <svg class="btn-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 19h16"/></svg>
            Export CSV
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Ticket / reported</th><th>Equipment</th><th>Issue</th><th>Requester</th><th>Technician</th><th>Priority</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($repairs as $repair): ?>
                <tr>
                    <td><a class="ticket-link" href="<?= url('repair_view.php?id=' . (int) $repair['id']) ?>"><?= e($repair['ticket_no']) ?></a><small><?= e(display_datetime($repair['date_reported'])) ?></small></td>
                    <td><strong><?= e($repair['asset_tag'] ?: $repair['equipment_description']) ?></strong><small><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? ''))) ?></small></td>
                    <td><strong><?= e($repair['issue_category']) ?></strong><small class="truncate"><?= e($repair['problem_description']) ?></small></td>
                    <td><?= e($repair['reported_by']) ?><small><?= e($repair['department']) ?></small></td>
                    <td><?= e($repair['assigned_technician'] ?: 'Unassigned') ?></td>
                    <td><span class="priority priority-<?= e(strtolower($repair['priority'])) ?>"><?= e($repair['priority']) ?></span></td>
                    <td><span class="badge badge-<?= e(status_class($repair['status'])) ?>"><?= e($repair['status']) ?></span></td>
                    <td><a class="icon-button" href="<?= url('repair_view.php?id=' . (int) $repair['id']) ?>" aria-label="Open repair">→</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$repairs): ?><tr><td colspan="8" class="empty-state"><strong>No matching repair requests</strong><span>Try clearing the filters or create a new request.</span></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Repair request pages">
            <?php for ($i = 1; $i <= $totalPages; $i++): $query = $_GET; $query['page'] = $i; ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= e(http_build_query($query)) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
