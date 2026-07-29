<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$repair = fetch_repair($pdo, $id);
if (!$repair) {
    http_response_code(404);
    exit('Repair request not found.');
}

$historyStmt = $pdo->prepare('SELECT * FROM repair_history WHERE repair_request_id = ? ORDER BY created_at DESC, id DESC');
$historyStmt->execute([$id]);
$history = $historyStmt->fetchAll();

$pageTitle = $repair['ticket_no'];
$pageSubtitle = 'Repair request details and service activity.';
require __DIR__ . '/includes/header.php';
?>
<div class="detail-toolbar">
    <div class="detail-status">
        <span class="badge badge-<?= e(status_class($repair['status'])) ?>"><?= e($repair['status']) ?></span>
        <span class="priority priority-<?= e(strtolower($repair['priority'])) ?>"><?= e($repair['priority']) ?> priority</span>
    </div>
    <div class="actions">
        <button class="btn btn-secondary" type="button" onclick="window.print()">
            <svg class="btn-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/></svg>
            Print
        </button>
        <a class="btn btn-primary" href="<?= url('repair_form.php?id=' . $id) ?>">
            <svg class="btn-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4z"/></svg>
            Update request
        </a>
    </div>
</div>

<div class="detail-layout">
    <div class="detail-main">
        <section class="card">
            <div class="card-header"><div><span class="section-kicker">Intake</span><h2>Issue information</h2></div></div>
            <div class="card-body">
                <dl class="detail-grid">
                    <div><dt>Equipment</dt><dd><?= e($repair['asset_tag'] ?: $repair['equipment_description']) ?></dd></div>
                    <div><dt>Equipment type</dt><dd><?= e($repair['equipment_type'] ?: $repair['equipment_description']) ?></dd></div>
                    <div><dt>Brand / model</dt><dd><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: '—') ?></dd></div>
                    <div><dt>Serial number</dt><dd><?= e($repair['serial_number'] ?: '—') ?></dd></div>
                    <div><dt>Reported by</dt><dd><?= e($repair['reported_by']) ?></dd></div>
                    <div><dt>Department / branch</dt><dd><?= e($repair['department']) ?></dd></div>
                    <div><dt>Contact</dt><dd><?= e($repair['contact_details'] ?: '—') ?></dd></div>
                    <div><dt>Date reported</dt><dd><?= e(display_datetime($repair['date_reported'])) ?></dd></div>
                    <div><dt>Issue category</dt><dd><?= e($repair['issue_category']) ?></dd></div>
                </dl>
                <div class="narrative">
                    <span>Problem description</span>
                    <p><?= nl2br(e($repair['problem_description'])) ?></p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><div><span class="section-kicker">Technical record</span><h2>Diagnosis and repair work</h2></div></div>
            <div class="card-body service-grid">
                <div class="narrative"><span>Diagnosis / findings</span><p><?= $repair['diagnosis'] ? nl2br(e($repair['diagnosis'])) : '<em>No diagnosis recorded yet.</em>' ?></p></div>
                <div class="narrative"><span>Action taken</span><p><?= $repair['action_taken'] ? nl2br(e($repair['action_taken'])) : '<em>No repair action recorded yet.</em>' ?></p></div>
                <div class="narrative"><span>Parts used / replaced</span><p><?= $repair['parts_used'] ? nl2br(e($repair['parts_used'])) : '<em>No parts recorded.</em>' ?></p></div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><div><span class="section-kicker">Audit trail</span><h2>Request activity</h2></div></div>
            <div class="timeline">
                <?php foreach ($history as $item): ?>
                    <article>
                        <i class="<?= $item['action_type'] === 'STATUS_CHANGED' ? 'status-change' : '' ?>"></i>
                        <div>
                            <div class="timeline-heading">
                                <strong><?= e($item['action_description']) ?></strong>
                                <time><?= e(display_datetime($item['created_at'])) ?></time>
                            </div>
                            <?php if ($item['notes']): ?><p><?= nl2br(e($item['notes'])) ?></p><?php endif; ?>
                            <small>By <?= e($item['user_name']) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$history): ?><div class="empty-state"><strong>No activity recorded</strong></div><?php endif; ?>
            </div>
        </section>
    </div>

    <aside class="detail-side">
        <section class="card sticky-card">
            <div class="card-header compact"><div><span class="section-kicker">Service summary</span><h2>Repair status</h2></div></div>
            <div class="card-body summary-list">
                <div><span>Status</span><strong class="badge badge-<?= e(status_class($repair['status'])) ?>"><?= e($repair['status']) ?></strong></div>
                <div><span>Assigned technician</span><strong><?= e($repair['assigned_technician'] ?: 'Unassigned') ?></strong></div>
                <div><span>Target completion</span><strong><?= e(display_date($repair['target_completion_date'], 'Not set')) ?></strong></div>
                <div><span>Completed</span><strong><?= e(display_datetime($repair['completed_at'], 'Not yet')) ?></strong></div>
            </div>
        </section>

        <section class="card">
            <div class="card-header compact"><div><span class="section-kicker">Release</span><h2>Handover details</h2></div></div>
            <div class="card-body summary-list">
                <div><span>Released to</span><strong><?= e($repair['released_to'] ?: 'Not yet released') ?></strong></div>
                <div><span>Release date</span><strong><?= e(display_datetime($repair['released_at'], '—')) ?></strong></div>
            </div>
        </section>

        <?php if ($repair['equipment_id']): ?>
        <section class="card">
            <div class="card-header compact"><div><span class="section-kicker">Asset record</span><h2><?= e($repair['asset_tag']) ?></h2></div></div>
            <div class="card-body summary-list">
                <div><span>Assigned to</span><strong><?= e($repair['assigned_to'] ?: 'Unassigned') ?></strong></div>
                <div><span>Equipment status</span><strong><?= e($repair['equipment_status']) ?></strong></div>
                <a class="btn btn-secondary btn-block" href="<?= url('equipment_form.php?id=' . (int) $repair['equipment_id']) ?>">Open equipment record</a>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
