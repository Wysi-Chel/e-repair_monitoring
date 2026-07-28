<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status NOT IN ('Completed','Cancelled')) AS open_count,
        SUM(status = 'Awaiting Parts') AS awaiting_parts,
        SUM(status = 'Ready for Release') AS ready_release,
        SUM(status = 'Completed' AND YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE())) AS completed_month,
        AVG(CASE WHEN status = 'Completed' AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, date_reported, completed_at) END) AS avg_hours
     FROM repair_requests"
)->fetch();

$equipmentStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'In Service') AS in_service,
        SUM(status = 'Under Repair') AS under_repair,
        SUM(status = 'For Replacement') AS replacement
     FROM equipment"
)->fetch();

$recentRepairs = $pdo->query(
    "SELECT r.*, e.asset_tag
     FROM repair_requests r
     LEFT JOIN equipment e ON e.id = r.equipment_id
     ORDER BY r.created_at DESC
     LIMIT 7"
)->fetchAll();

$statusCounts = array_fill_keys(REPAIR_STATUSES, 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM repair_requests GROUP BY status')->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}
$maxStatusCount = max(1, ...array_values($statusCounts));

$technicians = $pdo->query(
    "SELECT assigned_technician, COUNT(*) AS total
     FROM repair_requests
     WHERE assigned_technician IS NOT NULL
       AND assigned_technician <> ''
       AND status NOT IN ('Completed','Cancelled')
     GROUP BY assigned_technician
     ORDER BY total DESC, assigned_technician
     LIMIT 5"
)->fetchAll();

$pageTitle = 'Repair Monitoring';
$pageSubtitle = 'Overview of equipment issues, repair progress, and service workload.';
require __DIR__ . '/includes/header.php';
?>
<section class="stat-grid">
    <article class="card stat stat-red">
        <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"/><path d="M10.3 3.5 2.6 17a2 2 0 0 0 1.8 3h15.2a2 2 0 0 0 1.8-3L13.7 3.5a2 2 0 0 0-3.4 0z"/></svg></div>
        <div><span>Open repairs</span><strong><?= (int) ($stats['open_count'] ?? 0) ?></strong><small>Currently requiring IT action</small></div>
    </article>
    <article class="card stat stat-amber">
        <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M4 7h16v13H4z"/><path d="M7 7V4h10v3M9 12h6"/></svg></div>
        <div><span>Awaiting parts</span><strong><?= (int) ($stats['awaiting_parts'] ?? 0) ?></strong><small>Pending parts or supplies</small></div>
    </article>
    <article class="card stat stat-blue">
        <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></div>
        <div><span>Ready for release</span><strong><?= (int) ($stats['ready_release'] ?? 0) ?></strong><small>For requester collection</small></div>
    </article>
    <article class="card stat stat-green">
        <div class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></div>
        <div><span>Completed this month</span><strong><?= (int) ($stats['completed_month'] ?? 0) ?></strong><small><?= $stats['avg_hours'] !== null ? e(number_format((float) $stats['avg_hours'] / 24, 1)) . ' day average' : 'No completed repairs yet' ?></small></div>
    </article>
</section>

<div class="dashboard-grid">
    <section class="card dashboard-main">
        <div class="card-header">
            <div><span class="section-kicker">Latest activity</span><h2>Recent repair requests</h2></div>
            <a class="btn btn-secondary btn-sm" href="<?= url('repairs.php') ?>">View all repairs</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Ticket</th><th>Equipment / issue</th><th>Requester</th><th>Priority</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentRepairs as $repair): ?>
                    <tr>
                        <td><a class="ticket-link" href="<?= url('repair_view.php?id=' . (int) $repair['id']) ?>"><?= e($repair['ticket_no']) ?></a><small><?= e(display_date($repair['date_reported'])) ?></small></td>
                        <td><strong><?= e($repair['asset_tag'] ?: $repair['equipment_description']) ?></strong><small><?= e($repair['issue_category']) ?></small></td>
                        <td><?= e($repair['reported_by']) ?><small><?= e($repair['department']) ?></small></td>
                        <td><span class="priority priority-<?= e(strtolower($repair['priority'])) ?>"><?= e($repair['priority']) ?></span></td>
                        <td><span class="badge badge-<?= e(status_class($repair['status'])) ?>"><?= e($repair['status']) ?></span></td>
                        <td><a class="icon-button" href="<?= url('repair_view.php?id=' . (int) $repair['id']) ?>" aria-label="Open <?= e($repair['ticket_no']) ?>">→</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentRepairs): ?><tr><td colspan="6" class="empty-state"><strong>No repair requests yet</strong><span>Create the first request to begin monitoring repairs.</span></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-header compact"><div><span class="section-kicker">Pipeline</span><h2>Repair status</h2></div></div>
            <div class="status-chart">
                <?php foreach (REPAIR_STATUSES as $status): ?>
                    <?php if ($status === 'Cancelled') continue; ?>
                    <a href="<?= url('repairs.php?status=' . urlencode($status)) ?>">
                        <span><?= e($status) ?><b><?= $statusCounts[$status] ?></b></span>
                        <i><em style="width: <?= (int) round(($statusCounts[$status] / $maxStatusCount) * 100) ?>%"></em></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card equipment-summary">
            <div class="card-header compact"><div><span class="section-kicker">Assets</span><h2>Equipment health</h2></div></div>
            <div class="equipment-total"><strong><?= (int) ($equipmentStats['total'] ?? 0) ?></strong><span>registered equipment</span></div>
            <div class="mini-stats">
                <a href="<?= url('equipment.php?status=In+Service') ?>"><b><?= (int) ($equipmentStats['in_service'] ?? 0) ?></b><span>In service</span></a>
                <a href="<?= url('equipment.php?status=Under+Repair') ?>"><b><?= (int) ($equipmentStats['under_repair'] ?? 0) ?></b><span>Under repair</span></a>
                <a href="<?= url('equipment.php?status=For+Replacement') ?>"><b><?= (int) ($equipmentStats['replacement'] ?? 0) ?></b><span>For replacement</span></a>
            </div>
        </section>

        <?php if ($technicians): ?>
        <section class="card">
            <div class="card-header compact"><div><span class="section-kicker">Workload</span><h2>Assigned technicians</h2></div></div>
            <div class="technician-list">
                <?php foreach ($technicians as $technician): ?>
                    <a href="<?= url('repairs.php?technician=' . urlencode($technician['assigned_technician'])) ?>">
                        <span class="user-avatar small"><?= e(strtoupper(substr($technician['assigned_technician'], 0, 1))) ?></span>
                        <span><strong><?= e($technician['assigned_technician']) ?></strong><small>Active repair tickets</small></span>
                        <b><?= (int) $technician['total'] ?></b>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
