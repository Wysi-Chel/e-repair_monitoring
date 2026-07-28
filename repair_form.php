<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_portal_auth();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$requestedEquipmentId = (int) ($_GET['equipment_id'] ?? 0);
$repair = null;
if ($id > 0) {
    $repair = fetch_repair($pdo, $id);
    if (!$repair) {
        http_response_code(404);
        exit('Repair request not found.');
    }
}

$equipment = $pdo->query(
    "SELECT * FROM equipment WHERE status <> 'Retired' ORDER BY asset_tag"
)->fetchAll();

$technicians = $pdo->query(
    "SELECT DISTINCT assigned_technician FROM repair_requests
     WHERE assigned_technician IS NOT NULL AND assigned_technician <> ''
     ORDER BY assigned_technician"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $repair ? 'Edit ' . $repair['ticket_no'] : 'New Repair Request';
$pageSubtitle = $repair ? 'Update the request, repair findings, and service details.' : 'Record an equipment issue for assessment by the IT department.';
require __DIR__ . '/includes/header.php';
?>
<form method="post" action="<?= url('repair_save.php') ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int) ($repair['id'] ?? 0) ?>">

    <div class="form-layout">
        <div class="form-main">
            <section class="card form-section">
                <div class="card-header"><div><span class="section-kicker">Request details</span><h2>Reported issue</h2></div></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group span-2">
                            <label for="equipment_id">Registered equipment</label>
                            <select id="equipment_id" name="equipment_id" data-equipment-select>
                                <option value="">Unregistered equipment / no asset tag</option>
                                <?php foreach ($equipment as $item): ?>
                                    <option
                                        value="<?= (int) $item['id'] ?>"
                                        data-description="<?= e(equipment_label($item)) ?>"
                                        <?= (int) ($repair['equipment_id'] ?? $requestedEquipmentId) === (int) $item['id'] ? 'selected' : '' ?>
                                    ><?= e(equipment_label($item)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help">Select an asset, or describe unregistered equipment below. <a href="<?= url('equipment_form.php') ?>">Register new equipment</a></p>
                        </div>
                        <div class="form-group span-2">
                            <label for="equipment_description">Equipment description <span>*</span></label>
                            <input id="equipment_description" name="equipment_description" maxlength="255" required value="<?= e($repair['equipment_description'] ?? '') ?>" placeholder="Example: Dell Latitude laptop, black, no asset tag">
                        </div>
                        <div class="form-group">
                            <label for="reported_by">Reported by <span>*</span></label>
                            <input id="reported_by" name="reported_by" maxlength="150" required value="<?= e($repair['reported_by'] ?? '') ?>" placeholder="Employee or requester name">
                        </div>
                        <div class="form-group">
                            <label for="department">Department / branch <span>*</span></label>
                            <input id="department" name="department" maxlength="120" required value="<?= e($repair['department'] ?? '') ?>" placeholder="Example: Accounting">
                        </div>
                        <div class="form-group">
                            <label for="contact_details">Contact details</label>
                            <input id="contact_details" name="contact_details" maxlength="150" value="<?= e($repair['contact_details'] ?? '') ?>" placeholder="Mobile, extension, or email">
                        </div>
                        <div class="form-group">
                            <label for="date_reported">Date and time reported <span>*</span></label>
                            <input id="date_reported" type="datetime-local" name="date_reported" required value="<?= e(input_datetime($repair['date_reported'] ?? null)) ?>">
                        </div>
                        <div class="form-group">
                            <label for="issue_category">Issue category <span>*</span></label>
                            <select id="issue_category" name="issue_category" required>
                                <?php foreach (ISSUE_CATEGORIES as $option): ?><option value="<?= e($option) ?>" <?= ($repair['issue_category'] ?? 'Hardware') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority <span>*</span></label>
                            <select id="priority" name="priority" required>
                                <?php foreach (REPAIR_PRIORITIES as $option): ?><option value="<?= e($option) ?>" <?= ($repair['priority'] ?? 'Normal') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group span-2">
                            <label for="problem_description">Problem description <span>*</span></label>
                            <textarea id="problem_description" name="problem_description" rows="5" required placeholder="Describe the symptoms, error messages, and circumstances of the issue."><?= e($repair['problem_description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($repair): ?>
            <section class="card form-section">
                <div class="card-header"><div><span class="section-kicker">Service record</span><h2>Diagnosis and repair</h2></div></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="assigned_technician">Assigned technician</label>
                            <input id="assigned_technician" name="assigned_technician" list="technician-list" maxlength="150" value="<?= e($repair['assigned_technician'] ?? '') ?>" placeholder="Technician name">
                            <datalist id="technician-list"><?php foreach ($technicians as $name): ?><option value="<?= e($name) ?>"><?php endforeach; ?></datalist>
                        </div>
                        <div class="form-group">
                            <label for="target_completion_date">Target completion</label>
                            <input id="target_completion_date" type="date" name="target_completion_date" value="<?= e($repair['target_completion_date'] ?? '') ?>">
                        </div>
                        <div class="form-group span-2">
                            <label for="diagnosis">Diagnosis / findings</label>
                            <textarea id="diagnosis" name="diagnosis" rows="4" placeholder="Technical findings after assessment"><?= e($repair['diagnosis'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group span-2">
                            <label for="action_taken">Action taken</label>
                            <textarea id="action_taken" name="action_taken" rows="4" placeholder="Repair, configuration, or corrective actions performed"><?= e($repair['action_taken'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="parts_used">Parts used / replaced</label>
                            <textarea id="parts_used" name="parts_used" rows="3" placeholder="Part names and quantities"><?= e($repair['parts_used'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="released_to">Released to</label>
                            <input id="released_to" name="released_to" maxlength="150" value="<?= e($repair['released_to'] ?? '') ?>" placeholder="Recipient name">
                        </div>
                        <div class="form-group">
                            <label for="released_at">Release date and time</label>
                            <input id="released_at" type="datetime-local" name="released_at" value="<?= $repair['released_at'] ? e(input_datetime($repair['released_at'])) : '' ?>">
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <aside class="form-side">
            <section class="card form-section sticky-card">
                <div class="card-header"><div><span class="section-kicker">Workflow</span><h2>Request status</h2></div></div>
                <div class="card-body">
                    <?php if ($repair): ?>
                        <div class="form-group">
                            <label for="status">Current status</label>
                            <select id="status" name="status">
                                <?php foreach (REPAIR_STATUSES as $option): ?><option value="<?= e($option) ?>" <?= $repair['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_note">Update note</label>
                            <textarea id="status_note" name="status_note" rows="4" placeholder="Optional note for the activity trail"></textarea>
                        </div>
                        <div class="current-status">
                            <span>Currently</span>
                            <strong class="badge badge-<?= e(status_class($repair['status'])) ?>"><?= e($repair['status']) ?></strong>
                            <small>Last updated <?= e(display_datetime($repair['updated_at'])) ?></small>
                        </div>
                    <?php else: ?>
                        <div class="new-ticket-preview">
                            <span>New ticket number</span>
                            <strong><?= e(next_ticket_number($pdo)) ?></strong>
                            <small>The request starts as Submitted.</small>
                        </div>
                    <?php endif; ?>

                    <div class="form-submit">
                        <button class="btn btn-primary btn-block" type="submit"><?= $repair ? 'Save request changes' : 'Create repair request' ?></button>
                        <a class="btn btn-secondary btn-block" href="<?= $repair ? url('repair_view.php?id=' . (int) $repair['id']) : url('repairs.php') ?>">Cancel</a>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
