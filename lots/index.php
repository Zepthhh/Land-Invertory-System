<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotNo = trim($_POST['lot_no'] ?? '');
    $surveyNo = trim($_POST['survey_no'] ?? '');
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowedStatuses = lot_statuses();

    if ($lotNo === '' || $surveyNo === '' || $barangayId <= 0 || $area <= 0 || !in_array($status, $allowedStatuses, true)) {
        set_flash('error', 'Please complete all lot fields with valid values.');
        redirect(app_url('/lots/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO lots (lot_no, survey_no, barangay_id, area_sqm, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssids', $lotNo, $surveyNo, $barangayId, $area, $status);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Lot added successfully.');
    redirect(app_url('/lots/index.php'));
}

$pageTitle = 'Lot Management';
$pageDescription = 'Register lots by barangay with exact area and status tracking.';

$barangays = fetch_barangays($mysqli);
$lotStatuses = lot_statuses();
$lotFormAction = app_url('/lots/index.php');
$lotFormValues = [
    'id' => '',
    'lot_no' => '',
    'survey_no' => '',
    'barangay_id' => '',
    'area_sqm' => '',
    'status' => 'Unapplied',
];
$lotSubmitLabel = 'Save Lot';
$lotShowBack = false;
$lotsResult = $mysqli->query("
    SELECT l.id, l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status, l.current_claimant, l.dominant_use
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    ORDER BY l.id DESC
");
$lots = $lotsResult ? $lotsResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/../includes/header.php';
?>
<section class="grid-form-table">
    <div class="panel">
        <h2 class="panel-title">Add Lot</h2>
        <?php if (!$barangays): ?>
            <div class="empty-state-fancy">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <h3>No Barangays Yet</h3>
                <p>Add a barangay first before creating lot records.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
                <p class="section-text" style="margin:0; line-height: 1.5; color: var(--text-muted);">Quick-add a lot, or use the full create page for more fields.</p>
                <a class="btn btn-secondary" style="justify-content: center;" href="<?= h(app_url('/lots/create.php')); ?>">Open Create Page</a>
            </div>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="actions-spread" style="margin-bottom: 15px;">
            <h2 class="panel-title" style="margin:0;">Lot List</h2>
            <span class="table-counter" id="lotCounter"><?= count($lots) ?> total lots</span>
        </div>

        <!-- Live Filter Bar -->
        <div class="table-filter-bar">
            <input type="text" id="filterText" placeholder="🔍  Search lot no, survey, barangay, claimant..." oninput="filterLots()">
            <select id="filterStatus" onchange="filterLots()">
                <option value="">All Statuses</option>
                <?php foreach ($lotStatuses as $s): ?>
                    <option value="<?= h($s) ?>"><?= h(get_status_label($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table-sticky" id="lotsTable">
                <thead>
                    <tr>
                        <th>Lot No</th>
                        <th>Survey No</th>
                        <th>Barangay</th>
                        <th>Claimant</th>
                        <th>Area (sqm)</th>
                        <th>Dominant Use</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="lotsBody">
                    <?php if ($lots): ?>
                        <?php foreach ($lots as $lot):
                            $statusClass = 'status-' . strtolower(str_replace(' ', '-', $lot['status']));
                            // Map Conflict to its class
                            if ($lot['status'] === 'Conflict') $statusClass = 'status-conflict';
                        ?>
                            <tr class="lot-row <?= h($statusClass) ?>"
                                data-lot="<?= h(strtolower($lot['lot_no'])) ?>"
                                data-survey="<?= h(strtolower($lot['survey_no'])) ?>"
                                data-barangay="<?= h(strtolower($lot['barangay_name'])) ?>"
                                data-claimant="<?= h(strtolower($lot['current_claimant'] ?? '')) ?>"
                                data-status="<?= h($lot['status']) ?>">
                                <td><strong><?= h($lot['lot_no']); ?></strong></td>
                                <td><?= h($lot['survey_no']); ?></td>
                                <td><?= h($lot['barangay_name']); ?></td>
                                <td><?= h($lot['current_claimant'] ?: '—'); ?></td>
                                <td><?= format_number((float) $lot['area_sqm']); ?></td>
                                <td><?= h($lot['dominant_use'] ?: '—'); ?></td>
                                <td><span class="<?= h(get_status_badge_class($lot['status'])); ?>"><?= h(get_status_label($lot['status'])); ?></span></td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="btn btn-secondary" href="<?= h(app_url('/lots/edit.php?id=' . $lot['id'])); ?>">Edit</a>
                                        <button class="btn btn-danger" type="button" onclick="showConfirm(<?= (int)$lot['id'] ?>, '<?= h(addslashes($lot['lot_no'])) ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Inline Confirm Row -->
                            <tr class="confirm-row" id="confirm-<?= (int)$lot['id'] ?>">
                                <td colspan="8">
                                    <div class="confirm-message">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                        Delete lot <strong id="confirm-label-<?= (int)$lot['id'] ?>"></strong>? This cannot be undone.
                                        <form method="post" action="<?= h(app_url('/lots/delete.php')); ?>" style="display:inline; margin:0;">
                                            <input type="hidden" name="id" value="<?= h((string) $lot['id']); ?>">
                                            <button class="btn btn-danger" type="submit" style="padding: 5px 14px; font-size: 0.82rem;">Yes, Delete</button>
                                        </form>
                                        <button class="btn btn-secondary" type="button" onclick="hideConfirm(<?= (int)$lot['id'] ?>)" style="padding: 5px 14px; font-size: 0.82rem;">Cancel</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="emptyRow">
                            <td colspan="8">
                                <div class="empty-state-fancy">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    <h3>No Lots Found</h3>
                                    <p>Add a lot using the form on the left or import from Excel.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <!-- Filter empty state (hidden by default) -->
                    <tr id="noResultsRow" style="display:none;">
                        <td colspan="8">
                            <div class="empty-state-fancy">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <h3>No Matching Lots</h3>
                                <p>Try a different keyword or status filter.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function filterLots() {
    const text = document.getElementById('filterText').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#lotsBody .lot-row');
    const noResults = document.getElementById('noResultsRow');
    let visible = 0;

    rows.forEach(row => {
        const lotMatch = row.dataset.lot.includes(text)
            || row.dataset.survey.includes(text)
            || row.dataset.barangay.includes(text)
            || row.dataset.claimant.includes(text);
        const statusMatch = !status || row.dataset.status === status;

        const show = lotMatch && statusMatch;
        row.style.display = show ? '' : 'none';
        // Also hide the confirm row when parent is hidden
        const confirmId = row.querySelector('[onclick]')?.getAttribute('onclick')?.match(/\d+/)?.[0];
        if (confirmId) {
            document.getElementById('confirm-' + confirmId).style.display = 'none';
        }
        if (show) visible++;
    });

    noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    document.getElementById('lotCounter').textContent = visible + ' of <?= count($lots) ?> lots';
}

function showConfirm(id, lotNo) {
    // Hide any other open confirms
    document.querySelectorAll('.confirm-row').forEach(r => r.classList.remove('active'));
    document.getElementById('confirm-label-' + id).textContent = '"' + lotNo + '"';
    document.getElementById('confirm-' + id).classList.add('active');
}

function hideConfirm(id) {
    document.getElementById('confirm-' + id).classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

