<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login
require_login();

$currentUserRole = get_current_user_role();

// Check for API actions (Survey Cache or Duplicate Lot checks)
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'survey_api') {
        header('Content-Type: application/json');
        $apiResult = $mysqli->query("
            SELECT survey_no, barangay_id, subdivision 
            FROM lots 
            WHERE survey_no IS NOT NULL AND survey_no != ''
            GROUP BY survey_no
        ");
        $cache = [];
        if ($apiResult) {
            while ($row = $apiResult->fetch_assoc()) {
                $cache[trim($row['survey_no'])] = [
                    'barangay_id' => $row['barangay_id'],
                    'subdivision' => $row['subdivision']
                ];
            }
        }
        echo json_encode($cache);
        exit;
    }
    
    if ($_GET['action'] === 'check_duplicate') {
        header('Content-Type: application/json');
        $lotNo = trim($_GET['lot_no'] ?? '');
        $brgyId = (int)($_GET['barangay_id'] ?? 0);
        $currentId = (int)($_GET['current_id'] ?? 0);
        
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM lots WHERE lot_no = ? AND barangay_id = ? AND id != ?");
        $exists = false;
        if ($stmt) {
            $stmt->bind_param('sii', $lotNo, $brgyId, $currentId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $exists = ($res['total'] ?? 0) > 0;
            $stmt->close();
        }
        echo json_encode(['exists' => $exists]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only Admin or Editor can create records
    require_role(['Admin', 'Editor']);
    
    $lotNo = trim($_POST['lot_no'] ?? '');
    $surveyNo = trim($_POST['survey_no'] ?? '');
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    // Additional optional inputs for quick-add (optional)
    $dominantUse = trim($_POST['dominant_use'] ?? '');
    $subdivision = trim($_POST['subdivision'] ?? '');
    $sheetRow = (($_POST['sheet_row'] ?? '') !== '') ? (int)$_POST['sheet_row'] : null;
    $allowedStatuses = lot_statuses();

    if ($lotNo === '' || $surveyNo === '' || $barangayId <= 0 || $area <= 0 || !in_array($status, $allowedStatuses, true)) {
        set_flash('error', 'Please complete all lot fields with valid values.');
        redirect(app_url('/lots/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO lots (lot_no, survey_no, barangay_id, area_sqm, status, dominant_use, subdivision, sheet_row, source_sheet) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $sourceSheet = 'Web Interface';
    $stmt->bind_param('ssidsssis', $lotNo, $surveyNo, $barangayId, $area, $status, $dominantUse, $subdivision, $sheetRow, $sourceSheet);
    
    if ($stmt->execute()) {
        log_action($mysqli, 'Create Lot (Quick)', "Quick-added lot $lotNo ($area sqm) in Barangay ID $barangayId.");
        set_flash('success', 'Lot added successfully.');
    } else {
        set_flash('error', 'Failed to save quick lot record.');
    }
    $stmt->close();

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
    'survey_claimant' => '',
    'tax_declarant' => '',
    'current_claimant' => '',
    'claimant_sex' => '',
    'current_address' => '',
    'representative' => '',
    'representative_address' => '',
    'supporting_docs' => '',
    'subdivision' => '',
    'approved_survey_plan' => '',
    'land_case' => '',
    'titling_interest' => '',
    'mode_of_acquisition' => '',
    'dominant_use' => '',
    'remarks' => '',
    'source_sheet' => '',
    'case_reference' => '',
    'sheet_row' => '',
];
$lotSubmitLabel = 'Save Lot';
$lotShowBack = false;

// Query selecting ALL database columns to support detail popup
$lotsResult = $mysqli->query("
    SELECT 
        l.id, l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status,
        l.survey_claimant, l.tax_declarant, l.current_claimant, l.claimant_sex, l.current_address,
        l.representative, l.representative_address, l.supporting_docs, l.subdivision, l.approved_survey_plan,
        l.land_case, l.titling_interest, l.mode_of_acquisition, l.dominant_use, l.remarks,
        l.source_sheet, l.case_reference, l.sheet_row
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
        <?php if ($currentUserRole === 'Viewer'): ?>
            <div class="empty-state-fancy" style="padding: 20px 10px;">
                <p style="color: var(--text-muted);">You are logged in as a <strong>Viewer</strong>. You do not have permission to add or modify lot records.</p>
            </div>
        <?php elseif (!$barangays): ?>
            <div class="empty-state-fancy">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <h3>No Barangays Yet</h3>
                <p>Add a barangay first before creating lot records.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
                <p class="section-text" style="margin:0; line-height: 1.5; color: var(--text-muted);">Quick-add a lot, or use the full create page for more fields.</p>
                <a class="btn btn-secondary" style="justify-content: center;" href="<?= h(app_url('/lots/create.php')); ?>">Open Full Create Page</a>
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
                            if ($lot['status'] === 'Conflict') $statusClass = 'status-conflict';
                        ?>
                            <tr class="lot-row <?= h($statusClass) ?>" onclick="openViewModalFromRow(this, event)"
                                data-lot="<?= h(strtolower($lot['lot_no'])) ?>"
                                data-survey="<?= h(strtolower($lot['survey_no'])) ?>"
                                data-barangay="<?= h(strtolower($lot['barangay_name'])) ?>"
                                data-claimant="<?= h(strtolower($lot['current_claimant'] ?? $lot['survey_claimant'] ?? '')) ?>"
                                data-status="<?= h($lot['status']) ?>"
                                data-details='<?= h(json_encode($lot)); ?>'>
                                <td><strong><?= h($lot['lot_no']); ?></strong></td>
                                <td><?= h($lot['survey_no']); ?></td>
                                <td><?= h($lot['barangay_name']); ?></td>
                                <td><?= h($lot['current_claimant'] ?: $lot['survey_claimant'] ?: '—'); ?></td>
                                <td><?= format_number((float) $lot['area_sqm']); ?></td>
                                <td><?= h($lot['dominant_use'] ?: '—'); ?></td>
                                <td>
                                    <span class="<?= h(get_status_badge_class($lot['status'])); ?>">
                                        <?= get_status_icon($lot['status']); ?> <?= h(get_status_label($lot['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="inline-actions">
                                        <button type="button" class="btn btn-secondary" onclick="openViewModal(this)" style="padding: 8px 12px; font-size: 0.88rem; min-height: 36px;">View</button>
                                        <?php if ($currentUserRole !== 'Viewer'): ?>
                                            <a class="btn btn-secondary" href="<?= h(app_url('/lots/edit.php?id=' . $lot['id'])); ?>">Edit</a>
                                            <button class="btn btn-danger" type="button" onclick="showConfirm(<?= (int)$lot['id'] ?>, '<?= h(addslashes($lot['lot_no'])) ?>')">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($currentUserRole !== 'Viewer'): ?>
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
                            <?php endif; ?>
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

<!-- Detailed View Modal -->
<div id="viewModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: var(--glass-blur);">
    <div class="modal-card" style="background: rgba(18, 27, 22, 0.95); border: 1px solid var(--panel-border); border-radius: 20px; width: 90%; max-width: 750px; box-shadow: 0 20px 50px rgba(0,0,0,0.6); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
        <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--panel-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #fff; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                🔎 Lot Details: <span id="m-title-lot-no" style="color: var(--primary);"></span>
            </h3>
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()" style="min-height: auto; padding: 6px 12px; font-size: 0.85rem;">Close</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; color: #fff;">
            <!-- Tabs in modal -->
            <div class="modal-tabs" style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px; overflow-x: auto; white-space: nowrap;">
                <button type="button" id="modal-tab-btn-basic" class="btn btn-primary" onclick="switchModalTab('basic')" style="padding: 6px 12px; min-height: auto; font-size: 0.85rem; flex-shrink: 0;">Technical Specs</button>
                <button type="button" id="modal-tab-btn-claimant" class="btn btn-secondary" onclick="switchModalTab('claimant')" style="padding: 6px 12px; min-height: auto; font-size: 0.85rem; flex-shrink: 0;">Claimants & GAD</button>
                <button type="button" id="modal-tab-btn-legal" class="btn btn-secondary" onclick="switchModalTab('legal')" style="padding: 6px 12px; min-height: auto; font-size: 0.85rem; flex-shrink: 0;">Legal & Files</button>
            </div>
            
            <!-- Modal Pane: Basic -->
            <div id="modal-pane-basic" class="modal-pane-content-pane">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Lot Number</span><strong id="m-lot-no" style="font-size: 1.05rem;"></strong></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Survey Number</span><strong id="m-survey-no" style="font-size: 1.05rem;"></strong></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Barangay</span><span id="m-barangay" style="font-weight: 500;"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Area (sqm)</span><span id="m-area" style="font-weight: 700; color: var(--primary);"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Subdivision</span><span id="m-subdivision"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Dominant Use</span><span id="m-dominant-use"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Source Sheet</span><span id="m-source-sheet" style="font-size: 0.88rem; color: #a78bfa;"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Sheet Excel Row</span><span id="m-sheet-row" style="font-size: 0.88rem;"></span></div>
                </div>
            </div>
            
            <!-- Modal Pane: Claimant -->
            <div id="modal-pane-claimant" class="modal-pane-content-pane" style="display:none;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Current Claimant</span><strong id="m-current-claimant" style="font-size: 1.05rem;"></strong></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Claimant Sex (GAD)</span><span id="m-claimant-sex" style="font-weight: 600;"></span></div>
                    <div style="grid-column: span 2;"><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Current Address</span><span id="m-current-address"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Original Survey Claimant</span><span id="m-survey-claimant"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Tax Declarant</span><span id="m-tax-declarant"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Representative</span><span id="m-representative"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Representative Address</span><span id="m-representative-address"></span></div>
                </div>
            </div>
            
            <!-- Modal Pane: Legal & Files -->
            <div id="modal-pane-legal" class="modal-pane-content-pane" style="display:none;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Status</span><span id="m-status"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Mode of Acquisition</span><span id="m-acquisition"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Titling Interest</span><span id="m-interest"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Land Case Status</span><span id="m-land-case"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Case Reference</span><span id="m-case-ref" style="color: #fca5a5;"></span></div>
                    
                    <div style="grid-column: span 2;">
                        <span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Legal/Operational Remarks</span>
                        <p id="m-remarks" style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 10px; margin-top: 6px; white-space: pre-wrap; font-size: 0.92rem; line-height: 1.5; border: 1px solid var(--panel-border);"></p>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; display: flex; flex-direction: column; justify-content: center; min-height: 80px;">
                        <strong style="font-size: 0.88rem; display: block; margin-bottom: 6px;">📁 Supporting Document:</strong>
                        <div id="m-doc-link"></div>
                    </div>
                    <div style="background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; display: flex; flex-direction: column; justify-content: center; min-height: 80px;">
                        <strong style="font-size: 0.88rem; display: block; margin-bottom: 6px;">🗺️ Approved Survey Plan:</strong>
                        <div id="m-plan-link"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--panel-border); background: rgba(0,0,0,0.25); display: flex; justify-content: flex-end; gap: 10px;">
            <?php if ($currentUserRole !== 'Viewer'): ?>
                <a id="m-edit-btn" href="#" class="btn btn-primary" style="padding: 8px 16px; min-height: auto; font-size: 0.85rem;">Edit Lot</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()" style="padding: 8px 16px; min-height: auto; font-size: 0.85rem;">Close</button>
        </div>
    </div>
</div>

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
    document.querySelectorAll('.confirm-row').forEach(r => r.classList.remove('active'));
    document.getElementById('confirm-label-' + id).textContent = '"' + lotNo + '"';
    document.getElementById('confirm-' + id).classList.add('active');
}

function hideConfirm(id) {
    document.getElementById('confirm-' + id).classList.remove('active');
}

// Modal View Functions
function openViewModal(element) {
    const row = element.tagName === 'TR' ? element : element.closest('tr');
    const details = JSON.parse(row.dataset.details);
    const appUrlBase = '<?= h(app_url()); ?>';

    // Populate fields
    document.getElementById('m-title-lot-no').textContent = details.lot_no;
    document.getElementById('m-lot-no').textContent = details.lot_no || '—';
    document.getElementById('m-survey-no').textContent = details.survey_no || '—';
    document.getElementById('m-barangay').textContent = details.barangay_name || '—';
    document.getElementById('m-area').textContent = parseFloat(details.area_sqm).toLocaleString('en-US', {minimumFractionDigits: 2}) + ' sqm';
    document.getElementById('m-subdivision').textContent = details.subdivision || '—';
    document.getElementById('m-dominant-use').textContent = details.dominant_use || '—';
    document.getElementById('m-source-sheet').textContent = details.source_sheet || '—';
    document.getElementById('m-sheet-row').textContent = details.sheet_row || '—';

    document.getElementById('m-current-claimant').textContent = details.current_claimant || '—';
    document.getElementById('m-claimant-sex').textContent = details.claimant_sex || '—';
    document.getElementById('m-current-address').textContent = details.current_address || '—';
    document.getElementById('m-survey-claimant').textContent = details.survey_claimant || '—';
    document.getElementById('m-tax-declarant').textContent = details.tax_declarant || '—';
    document.getElementById('m-representative').textContent = details.representative || '—';
    document.getElementById('m-representative-address').textContent = details.representative_address || '—';

    // Map status label with icons
    const statusLabel = details.status === 'Conflict' ? 'With land claims and conflicts' : details.status;
    let statusClass = 'badge amber';
    let statusIcon = '🔍';
    if (details.status === 'Titled') { statusClass = 'badge green'; statusIcon = '✅'; }
    else if (details.status === 'Applied') { statusClass = 'badge blue'; statusIcon = '📄'; }
    else if (details.status === 'Conflict') { statusClass = 'badge red'; statusIcon = '⚠️'; }
    document.getElementById('m-status').innerHTML = `<span class="${statusClass}">${statusIcon} ${statusLabel}</span>`;
    
    document.getElementById('m-acquisition').textContent = details.mode_of_acquisition || '—';
    document.getElementById('m-interest').textContent = details.titling_interest || '—';
    document.getElementById('m-land-case').textContent = details.land_case || '—';
    document.getElementById('m-case-ref').textContent = details.case_reference || '—';
    document.getElementById('m-remarks').textContent = details.remarks || 'No remarks recorded for this lot.';

    // Documents
    const docLinkDiv = document.getElementById('m-doc-link');
    if (details.supporting_docs) {
        const fileBase = details.supporting_docs.split('/').pop();
        docLinkDiv.innerHTML = `<a href="${appUrlBase}/${details.supporting_docs}" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 10px; min-height:auto;">📥 Download Document (${fileBase})</a>`;
    } else {
        docLinkDiv.innerHTML = '<span style="color: var(--text-muted); font-size:0.85rem;">No files uploaded</span>';
    }

    const planLinkDiv = document.getElementById('m-plan-link');
    if (details.approved_survey_plan) {
        const fileBase = details.approved_survey_plan.split('/').pop();
        planLinkDiv.innerHTML = `<a href="${appUrlBase}/${details.approved_survey_plan}" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 10px; min-height:auto;">📥 Download Survey Plan (${fileBase})</a>`;
    } else {
        planLinkDiv.innerHTML = '<span style="color: var(--text-muted); font-size:0.85rem;">No plan uploaded</span>';
    }

    // Set Edit button url
    const editBtn = document.getElementById('m-edit-btn');
    if (editBtn) {
        editBtn.href = `${appUrlBase}/lots/edit.php?id=${details.id}`;
    }

    // Display modal
    switchModalTab('basic');
    document.getElementById('viewModal').style.display = 'flex';
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function openViewModalFromRow(row, event) {
    if (event.target.closest('button') || event.target.closest('a') || event.target.closest('.confirm-row') || event.target.closest('.confirm-message')) {
        return;
    }
    openViewModal(row);
}

function switchModalTab(tabId) {
    document.querySelectorAll('.modal-pane-content-pane').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.modal-tabs .btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-secondary');
    });

    document.getElementById('modal-pane-' + tabId).style.display = 'block';
    const activeBtn = document.getElementById('modal-tab-btn-' + tabId);
    activeBtn.classList.remove('btn-secondary');
    activeBtn.classList.add('btn-primary');
}

// Close modal when clicking outside card
window.onclick = function(event) {
    const modal = document.getElementById('viewModal');
    if (event.target === modal) {
        closeViewModal();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
