<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$item = null;
$repairHistory = [];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        http_response_code(404);
        exit('Equipment record not found.');
    }
    $repairs = $pdo->prepare('SELECT * FROM repair_requests WHERE equipment_id = ? ORDER BY date_reported DESC LIMIT 10');
    $repairs->execute([$id]);
    $repairHistory = $repairs->fetchAll();
}

$pageTitle = $item ? 'Edit ' . $item['asset_tag'] : 'Register Equipment';
$pageSubtitle = $item ? 'Update asset ownership, location, and operating status.' : 'Add an asset to the IT equipment registry.';
require __DIR__ . '/includes/header.php';
?>
<div class="form-layout">
    <form class="form-main" method="post" action="<?= url('equipment_save.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">

        <section class="card form-section">
            <div class="card-header"><div><span class="section-kicker">Asset record</span><h2>Equipment information</h2></div></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="asset_tag">Asset tag <span>*</span></label>
                        <input id="asset_tag" name="asset_tag" maxlength="60" required value="<?= e($item['asset_tag'] ?? '') ?>" placeholder="Example: IT-LAP-001">
                    </div>
                    <div class="form-group">
                        <label for="equipment_type">Equipment type <span>*</span></label>
                        <select id="equipment_type" name="equipment_type" required>
                            <?php foreach (EQUIPMENT_TYPES as $option): ?><option value="<?= e($option) ?>" <?= ($item['equipment_type'] ?? 'Desktop Computer') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="brand">Brand</label>
                        <input id="brand" name="brand" maxlength="100" value="<?= e($item['brand'] ?? '') ?>" placeholder="Example: Dell">
                    </div>
                    <div class="form-group">
                        <label for="model">Model</label>
                        <input id="model" name="model" maxlength="120" value="<?= e($item['model'] ?? '') ?>" placeholder="Example: OptiPlex 7010">
                    </div>
                    <div class="form-group">
                        <label for="serial_number">Serial number</label>
                        <input id="serial_number" name="serial_number" maxlength="120" value="<?= e($item['serial_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="purchase_date">Purchase date</label>
                        <input id="purchase_date" type="date" name="purchase_date" value="<?= e($item['purchase_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="assigned_to">Assigned to</label>
                        <input id="assigned_to" name="assigned_to" maxlength="150" value="<?= e($item['assigned_to'] ?? '') ?>" placeholder="Employee or team">
                    </div>
                    <div class="form-group">
                        <label for="department">Department / branch</label>
                        <input id="department" name="department" maxlength="120" value="<?= e($item['department'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Equipment status <span>*</span></label>
                        <select id="status" name="status" required>
                            <?php foreach (EQUIPMENT_STATUSES as $option): ?><option value="<?= e($option) ?>" <?= ($item['status'] ?? 'In Use') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="Specifications, warranty, accessories, or other notes"><?= e($item['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </section>

        <div class="form-action-bar">
            <a class="btn btn-secondary" href="<?= url('equipment.php') ?>">Cancel</a>
            <button class="btn btn-primary" type="submit"><?= $item ? 'Save equipment changes' : 'Register equipment' ?></button>
        </div>
    </form>

    <aside class="form-side">
        <?php if ($item): ?>
        <section class="card sticky-card">
            <div class="card-header compact"><div><span class="section-kicker">Service history</span><h2>Recent repairs</h2></div></div>
            <div class="related-list">
                <?php foreach ($repairHistory as $repair): ?>
                    <a href="<?= url('repair_view.php?id=' . (int) $repair['id']) ?>">
                        <span><strong><?= e($repair['ticket_no']) ?></strong><small><?= e($repair['issue_category']) ?> · <?= e(display_date($repair['date_reported'])) ?></small></span>
                        <span class="badge badge-<?= e(status_class($repair['status'])) ?>"><?= e($repair['status']) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$repairHistory): ?><div class="empty-state compact"><strong>No repair history</strong><span>This equipment has no linked requests.</span></div><?php endif; ?>
            </div>
            <div class="card-body card-action">
                <a class="btn btn-primary btn-block" href="<?= url('repair_form.php?equipment_id=' . $id) ?>">Create repair request</a>
            </div>
        </section>
        <?php else: ?>
        <section class="card sticky-card">
            <div class="card-body tip-card">
                <span class="tip-icon">i</span>
                <h2>Why register equipment?</h2>
                <p>Registered assets keep assignment details and repair history together, making repeated faults easier to identify.</p>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
