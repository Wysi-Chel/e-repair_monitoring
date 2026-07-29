<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(e.asset_tag LIKE :q_asset OR e.serial_number LIKE :q_serial OR e.brand LIKE :q_brand OR e.model LIKE :q_model OR e.assigned_to LIKE :q_assigned OR e.department LIKE :q_department)';
    foreach ([':q_asset', ':q_serial', ':q_brand', ':q_model', ':q_assigned', ':q_department'] as $placeholder) {
        $params[$placeholder] = '%' . $q . '%';
    }
}
if (is_valid_option($status, EQUIPMENT_STATUSES)) {
    $where[] = 'e.status = :status';
    $params[':status'] = $status;
}
if ($type !== '') {
    $where[] = 'e.equipment_type = :type';
    $params[':type'] = $type;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT e.*,
            COUNT(r.id) AS repair_count,
            SUM(r.status NOT IN ('Completed','Cancelled')) AS active_repairs,
            MAX(r.date_reported) AS last_repair
     FROM equipment e
     LEFT JOIN repair_requests r ON r.equipment_id = e.id
     $whereSql
     GROUP BY e.id
     ORDER BY FIELD(e.status, 'Under Repair','For Replacement','Completed','In Service','Retired'), e.asset_tag"
);
$stmt->execute($params);
$equipment = $stmt->fetchAll();

$types = $pdo->query('SELECT DISTINCT equipment_type FROM equipment ORDER BY equipment_type')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Equipment Registry';
$pageSubtitle = 'Maintain asset details and review repair frequency for company equipment.';
require __DIR__ . '/includes/header.php';
?>
<section class="card filter-card">
    <form class="filters equipment-filters" method="get">
        <div class="form-group search-field">
            <label for="q">Search equipment</label>
            <div class="input-with-icon">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="q" name="q" value="<?= e($q) ?>" placeholder="Asset tag, serial, brand, or assigned employee">
            </div>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (EQUIPMENT_STATUSES as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="type">Equipment type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                <?php foreach ($types as $option): ?><option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <a class="btn btn-secondary" href="<?= url('equipment.php') ?>">Clear</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-header">
        <div><span class="section-kicker">Asset inventory</span><h2><?= count($equipment) ?> equipment record<?= count($equipment) === 1 ? '' : 's' ?></h2></div>
        <a class="btn btn-primary btn-sm" href="<?= url('equipment_form.php') ?>">
            <svg class="btn-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Register equipment
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Asset</th><th>Equipment</th><th>Assignment</th><th>Serial number</th><th>Repair history</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($equipment as $item): ?>
                <tr>
                    <td><a class="ticket-link" href="<?= url('equipment_form.php?id=' . (int) $item['id']) ?>"><?= e($item['asset_tag']) ?></a><small><?= e($item['department'] ?: 'No department') ?></small></td>
                    <td><strong><?= e($item['equipment_type']) ?></strong><small><?= e(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '')) ?: 'No brand/model') ?></small></td>
                    <td><?= e($item['assigned_to'] ?: 'Unassigned') ?><small><?= e($item['location'] ?: 'No location') ?></small></td>
                    <td><?= e($item['serial_number'] ?: '—') ?></td>
                    <td><strong><?= (int) $item['repair_count'] ?> total</strong><small><?= (int) $item['active_repairs'] ?> active · <?= e(display_date($item['last_repair'], 'Never repaired')) ?></small></td>
                    <td><span class="badge badge-equipment-<?= e(status_class($item['status'])) ?>"><?= e($item['status']) ?></span></td>
                    <td><a class="icon-button" href="<?= url('equipment_form.php?id=' . (int) $item['id']) ?>" aria-label="Edit equipment">→</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$equipment): ?><tr><td colspan="7" class="empty-state"><strong>No equipment records found</strong><span>Register equipment to connect it to repair requests.</span></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
