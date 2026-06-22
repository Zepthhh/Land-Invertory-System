<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['Admin', 'Editor']);
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $allowedTypes = land_use_types();

    if ($barangayId <= 0 || $area <= 0 || !in_array($type, $allowedTypes, true)) {
        set_flash('error', 'Please complete all land use fields with valid values.');
        redirect(app_url('/land_use/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO land_use (barangay_id, type, area_sqm) VALUES (?, ?, ?)');
    $stmt->bind_param('isd', $barangayId, $type, $area);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Create Land Use', "Created Land Use: $type ($area sqm) in Barangay ID $barangayId.");
    set_flash('success', 'Land use entry added successfully.');
    redirect(app_url('/land_use/index.php'));
}

$pageTitle = 'Land Use Management';
$pageDescription = 'Record area deductions such as roads, church, school site, plaza, and similar uses.';

$barangays = fetch_barangays($mysqli);
$landUseTypes = land_use_types();
$infraTypes = land_use_infra_types();
$landUseFormAction = app_url('/land_use/index.php');
$landUseFormValues = [
    'id' => '',
    'barangay_id' => '',
    'type' => 'Road',
    'area_sqm' => '',
];
$landUseSubmitLabel = 'Save Land Use';
$landUseShowBack = false;
$landUseResult = $mysqli->query("
    SELECT u.id, b.name AS barangay_name, b.id AS barangay_id, u.type, u.area_sqm
    FROM land_use u
    INNER JOIN barangay b ON b.id = u.barangay_id
    ORDER BY b.name ASC, u.type ASC
");
$landUses = $landUseResult ? $landUseResult->fetch_all(MYSQLI_ASSOC) : [];

$currentUserRole = get_current_user_role();

// Build list of unique barangays that have land use entries (for filter dropdown)
$brgyFilterList = [];
foreach ($landUses as $lu) {
    $brgyFilterList[$lu['barangay_id']] = $lu['barangay_name'];
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="grid-form-table">
    <div class="panel">
        <h2 class="panel-title">Add Land Use</h2>
        <?php if ($currentUserRole === 'Viewer'): ?>
            <div class="empty-state-fancy" style="padding: 20px 10px;">
                <p style="color: var(--text-muted);">You are logged in as a <strong>Viewer</strong>. You do not have permission to add or modify land use entries.</p>
            </div>
        <?php elseif (!$barangays): ?>
            <div class="empty-state">Add a barangay first before recording land use deductions.</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
                <p class="section-text" style="margin:0;">Quick entry is still available here, and the dedicated create page is ready if you prefer a separate form.</p>
                <a class="btn btn-secondary" style="justify-content: center;" href="<?= h(app_url('/land_use/create.php')); ?>">Open Create Page</a>
            </div>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="actions-spread" style="margin-bottom: 15px;">
            <h2 class="panel-title" style="margin:0;">Land Use List</h2>
            <div style="display:flex;gap:10px;align-items:center;">
                <span class="table-counter" id="luCounter"><?= count($landUses); ?> entries</span>
                <?php if ($currentUserRole !== 'Viewer' && $landUses): ?>
                    <button class="btn btn-export" onclick="exportLandUseCSV()" style="padding: 7px 14px; font-size: 0.82rem; min-height: 34px;">
                        ⬇ Export CSV
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Live Filter Bar -->
        <div class="filter-toolbar">
            <input type="text" id="luFilterText" placeholder="🔍  Search barangay, type..." oninput="filterLandUse()">
            <select id="luFilterBrgy" onchange="filterLandUse()">
                <option value="">All Barangays</option>
                <?php foreach ($brgyFilterList as $bId => $bName): ?>
                    <option value="<?= h((string)$bId); ?>"><?= h($bName); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="luFilterType" onchange="filterLandUse()">
                <option value="">All Types</option>
                <?php foreach ($landUseTypes as $t): ?>
                    <option value="<?= h($t); ?>"><?= h($t); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="table-wrap">
            <table class="table-compact table-sticky" id="luTable">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Area (sqm)</th>
                        <?php if ($currentUserRole !== 'Viewer'): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="luBody">
                    <?php if ($landUses): ?>
                        <?php foreach ($landUses as $entry):
                            $isInfra = in_array($entry['type'], $infraTypes, true);
                            $catLabel = $isInfra ? 'Infrastructure' : 'Community';
                            $catClass = $isInfra ? 'infra' : 'community';
                        ?>
                            <tr class="lu-row"
                                data-brgy="<?= h((string)$entry['barangay_id']); ?>"
                                data-name="<?= h(strtolower((string)($entry['barangay_name'] ?? ''))); ?>"
                                data-type="<?= h($entry['type']); ?>">
                                <td><strong><?= h($entry['barangay_name']); ?></strong></td>
                                <td><?= h($entry['type']); ?></td>
                                <td><span class="type-badge <?= $catClass; ?>"><?= $catLabel; ?></span></td>
                                <td><?= format_number((float) $entry['area_sqm']); ?></td>
                                <?php if ($currentUserRole !== 'Viewer'): ?>
                                    <td>
                                        <div class="inline-actions">
                                            <a class="btn btn-secondary" href="<?= h(app_url('/land_use/edit.php?id=' . $entry['id'])); ?>">Edit</a>
                                            <button class="btn btn-danger" type="button" onclick="showLUConfirm(<?= (int)$entry['id']; ?>, '<?= h(addslashes($entry['type'])); ?>', '<?= h(addslashes($entry['barangay_name'])); ?>')">Delete</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($currentUserRole !== 'Viewer'): ?>
                                <tr class="confirm-row" id="lu-confirm-<?= (int)$entry['id']; ?>">
                                    <td colspan="5">
                                        <div class="confirm-message">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                            Delete <strong id="lu-confirm-label-<?= (int)$entry['id']; ?>"></strong>? This cannot be undone.
                                            <form method="post" action="<?= h(app_url('/land_use/delete.php')); ?>" style="display:inline; margin:0;">
                                                <input type="hidden" name="id" value="<?= h((string) $entry['id']); ?>">
                                                <button class="btn btn-danger" type="submit" style="padding: 5px 14px; font-size: 0.82rem;">Yes, Delete</button>
                                            </form>
                                            <button class="btn btn-secondary" type="button" onclick="hideLUConfirm(<?= (int)$entry['id']; ?>)" style="padding: 5px 14px; font-size: 0.82rem;">Cancel</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state-fancy">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h18v18H3z"></path><path d="M3 9h18M3 15h18M9 3v18"></path></svg>
                                    <h3>No Land Use Entries</h3>
                                    <p>Add land use deductions using the form on the left.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr id="luNoResultsRow" style="display:none;">
                        <td colspan="5">
                            <div class="empty-state-fancy" style="padding: 30px;">
                                <h3>No Matching Entries</h3>
                                <p>Try a different filter.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function filterLandUse() {
    const text = document.getElementById('luFilterText').value.toLowerCase();
    const brgy = document.getElementById('luFilterBrgy').value;
    const type = document.getElementById('luFilterType').value;
    const rows = document.querySelectorAll('#luBody .lu-row');
    const noResults = document.getElementById('luNoResultsRow');
    let visible = 0;

    rows.forEach(row => {
        const nameMatch = row.dataset.name.includes(text);
        const brgyMatch = !brgy || row.dataset.brgy === brgy;
        const typeMatch = !type || row.dataset.type === type;
        const show = nameMatch && brgyMatch && typeMatch;
        row.style.display = show ? '' : 'none';
        const nextRow = row.nextElementSibling;
        if (nextRow && nextRow.classList.contains('confirm-row')) {
            nextRow.style.display = 'none';
            nextRow.classList.remove('active');
        }
        if (show) visible++;
    });

    noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    document.getElementById('luCounter').textContent = visible + ' of <?= count($landUses); ?> entries';
}

function showLUConfirm(id, type, barangay) {
    document.querySelectorAll('.confirm-row').forEach(r => r.classList.remove('active'));
    document.getElementById('lu-confirm-label-' + id).textContent = '"' + type + ' (' + barangay + ')"';
    document.getElementById('lu-confirm-' + id).classList.add('active');
}

function hideLUConfirm(id) {
    document.getElementById('lu-confirm-' + id).classList.remove('active');
}

function exportLandUseCSV() {
    const rows = document.querySelectorAll('#luBody .lu-row');
    let csv = 'Barangay,Type,Category,Area (sqm)\n';
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const cells = row.querySelectorAll('td');
        const barangay = cells[0].textContent.trim();
        const type = cells[1].textContent.trim();
        const cat = cells[2].textContent.trim();
        const area = cells[3].textContent.trim();
        csv += `"${barangay}","${type}","${cat}","${area}"\n`;
    });
    downloadCSV(csv, 'land_use_export.csv');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
