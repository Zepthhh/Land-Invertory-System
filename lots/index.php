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

$searchQuery = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterMunicipalityId = (int)($_GET['municipality_id'] ?? 0);
$filterBarangayId = (int)($_GET['barangay_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$whereClauses = [];
$params = [];
$types = '';

if ($searchQuery !== '') {
    $whereClauses[] = '(l.lot_no LIKE ? OR l.survey_no LIKE ? OR l.current_claimant LIKE ? OR l.survey_claimant LIKE ?)';
    $likeQuery = '%' . $searchQuery . '%';
    $params = array_merge($params, [$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    $types .= 'ssss';
}
if ($filterStatus !== '') {
    $whereClauses[] = 'l.status = ?';
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterMunicipalityId > 0) {
    $whereClauses[] = 'b.municipality_id = ?';
    $params[] = $filterMunicipalityId;
    $types .= 'i';
}
if ($filterBarangayId > 0) {
    $whereClauses[] = 'l.barangay_id = ?';
    $params[] = $filterBarangayId;
    $types .= 'i';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Count total
$countQuery = "SELECT COUNT(*) AS total FROM lots l INNER JOIN barangay b ON b.id = l.barangay_id $whereSql";
$stmt = $mysqli->prepare($countQuery);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalFiltered = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, ceil($totalFiltered / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT 
        l.id, l.lot_no, l.survey_no, b.name AS barangay_name, b.id AS barangay_id, l.area_sqm, l.status,
        l.survey_claimant, l.tax_declarant, l.current_claimant, l.claimant_sex, l.current_address,
        l.representative, l.representative_address, l.supporting_docs, l.subdivision, l.approved_survey_plan,
        l.land_case, l.titling_interest, l.mode_of_acquisition, l.dominant_use, l.remarks,
        l.source_sheet, l.case_reference, l.sheet_row
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    $whereSql
    ORDER BY l.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $mysqli->prepare($sql);
$limitTypes = $types . 'ii';
$limitParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$lotsResult = $stmt->get_result();
$lots = $lotsResult ? $lotsResult->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    <?php if ($currentUserRole !== 'Viewer' && $barangays): ?>
        <button type="button" id="toggleFormBtn" class="btn btn-primary" onclick="toggleAddForm()" style="padding: 9px 18px; font-size: 0.9rem; min-height: 38px; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Lot
        </button>
        <a class="btn btn-secondary" href="<?= h(app_url('/lots/create.php')); ?>" style="padding: 9px 18px; font-size: 0.9rem; min-height: 38px;">
            📋 Full Create Page
        </a>
    <?php endif; ?>
</div>

<!-- Collapsible Add Lot Form -->
<div id="addLotFormPanel" style="display:none; margin-bottom: 20px;">
    <div class="panel" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h2 class="panel-title" style="margin:0;">Add Lot</h2>
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()" style="padding: 6px 14px; font-size: 0.82rem; min-height: 32px;">✕ Close</button>
        </div>
        <?php if ($currentUserRole === 'Viewer'): ?>
            <p style="color: var(--text-muted);">You are logged in as a <strong>Viewer</strong>. You do not have permission to add or modify lot records.</p>
        <?php elseif (!$barangays): ?>
            <div class="empty-state-fancy">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <h3>No Barangays Yet</h3>
                <p>Add a barangay first before creating lot records.</p>
            </div>
        <?php else: ?>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>
</div>

<section style="width:100%;">

    <div class="panel">
        <div class="actions-spread" style="margin-bottom: 15px;">
            <h2 class="panel-title" style="margin:0;">Lot List</h2>
            <div style="display:flex;gap:10px;align-items:center;">
                <span class="table-counter" id="lotCounter"><?= $totalFiltered ?> total matching lots</span>
                <?php if ($lots): ?>
                    <button class="btn btn-export" onclick="exportLotsCSV()" style="padding: 7px 14px; font-size: 0.82rem; min-height: 34px;">⬇ Export CSV</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="<?= h(app_url('/lots/index.php')) ?>" class="table-filter-bar" style="display:flex; gap:10px; margin-bottom: 15px; align-items: center;">
            <input type="text" name="q" value="<?= h($searchQuery) ?>" placeholder="🔍  Search lot no, survey, claimant..." style="flex:1;">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($lotStatuses as $s): ?>
                    <option value="<?= h($s) ?>" <?= $s === $filterStatus ? 'selected' : '' ?>><?= h(get_status_label($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php
            $municipalitiesRes = $mysqli->query("SELECT id, name FROM municipality ORDER BY name ASC");
            $municipalities = $municipalitiesRes ? $municipalitiesRes->fetch_all(MYSQLI_ASSOC) : [];
            ?>
            <select name="municipality_id" id="munFilter" onchange="filterBarangayDropdown(); this.form.submit();">
                <option value="">All Municipalities</option>
                <?php foreach ($municipalities as $m): ?>
                    <option value="<?= h((string)$m['id']); ?>" <?= (string)$m['id'] === (string)$filterMunicipalityId ? 'selected' : '' ?>><?= h($m['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="barangay_id" id="brgyFilter" onchange="this.form.submit()">
                <option value="">All Barangays</option>
                <?php foreach ($barangays as $b): ?>
                    <option value="<?= h((string)$b['id']); ?>" data-mun-id="<?= h((string)$b['municipality_id']); ?>" <?= (string)$b['id'] === (string)$filterBarangayId ? 'selected' : '' ?>><?= h($b['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
        <div class="table-wrap">
<script>
// Filter barangay dropdown based on selected municipality
function filterBarangayDropdown() {
    const munId = document.getElementById('munFilter').value;
    const brgySelect = document.getElementById('brgyFilter');
    let hasSelectedVisible = false;
    
    Array.from(brgySelect.options).forEach(opt => {
        if (opt.value === "") return; // Skip "All Barangays"
        const optMunId = opt.getAttribute('data-mun-id');
        const show = !munId || optMunId === munId;
        opt.style.display = show ? '' : 'none';
        
        // Deselect if currently selected option becomes hidden
        if (opt.selected && !show) {
            brgySelect.value = "";
        }
        if (opt.selected && show) {
            hasSelectedVisible = true;
        }
    });
}
// Run on load
document.addEventListener('DOMContentLoaded', filterBarangayDropdown);
</script>
            <table class="table-sticky" id="lotsTable" style="min-width: 700px;">
                <thead>
                    <tr>
                        <th style="padding: 10px 12px;">Lot No</th>
                        <th style="padding: 10px 12px;">Survey No</th>
                        <th style="padding: 10px 12px;">Barangay</th>
                        <th style="padding: 10px 12px;">Claimant</th>
                        <th style="padding: 10px 12px;">Area (sqm)</th>
                        <th style="padding: 10px 12px;">Dominant Use</th>
                        <th style="padding: 10px 12px;">Status</th>
                        <th style="padding: 10px 12px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="lotsBody">
                    <?php if ($lots): ?>
                        <?php foreach ($lots as $lot):
                            $statusClass = 'status-' . strtolower(str_replace(' ', '-', $lot['status']));
                            if ($lot['status'] === 'Conflict') $statusClass = 'status-conflict';
                        ?>
                            <tr class="lot-row <?= h($statusClass) ?>" onclick="openViewModalFromRow(this, event)"
                                data-lot="<?= h(strtolower((string)($lot['lot_no'] ?? ''))) ?>"
                                data-survey="<?= h(strtolower((string)($lot['survey_no'] ?? ''))) ?>"
                                data-barangay="<?= h(strtolower((string)($lot['barangay_name'] ?? ''))) ?>"
                                data-barangayid="<?= h((string)($lot['barangay_id'] ?? '')) ?>"
                                data-claimant="<?= h(strtolower((string)($lot['current_claimant'] ?? $lot['survey_claimant'] ?? ''))) ?>"
                                data-status="<?= h($lot['status']) ?>"
                                data-id="<?= (int)$lot['id'] ?>"
                                style="cursor:pointer;">
                                <td style="padding: 10px 12px;"><strong><?= h($lot['lot_no']); ?></strong></td>
                                <td style="padding: 10px 12px; font-size: 0.85rem;"><?= h($lot['survey_no']); ?></td>
                                <td style="padding: 10px 12px;"><?= h($lot['barangay_name']); ?></td>
                                <td style="padding: 10px 12px;"><?= h($lot['current_claimant'] ?: $lot['survey_claimant'] ?: '—'); ?></td>
                                <td style="padding: 10px 12px; white-space: nowrap;"><?= format_number((float) $lot['area_sqm']); ?></td>
                                <td style="padding: 10px 12px;"><?= h($lot['dominant_use'] ?: '—'); ?></td>
                                <td style="padding: 10px 12px;">
                                    <span class="<?= h(get_status_badge_class($lot['status'])); ?>">
                                        <?= get_status_icon($lot['status']); ?> <?= h(get_status_label($lot['status'])); ?>
                                    </span>
                                </td>
                                <td style="padding: 8px 10px;">
                                    <div class="inline-actions" style="gap: 5px;">
                                        <button type="button" class="btn btn-secondary" onclick="event.stopPropagation(); openViewById(<?= (int)$lot['id'] ?>)" style="padding: 5px 10px; font-size: 0.8rem; min-height: 30px; min-width: 50px;">View</button>
                                        <?php if ($currentUserRole !== 'Viewer'): ?>
                                            <a class="btn btn-secondary" href="<?= h(app_url('/lots/edit.php?id=' . $lot['id'])); ?>" style="padding: 5px 10px; font-size: 0.8rem; min-height: 30px; min-width: 46px;">Edit</a>
                                            <button class="btn btn-danger" type="button" onclick="showConfirm(<?= (int)$lot['id'] ?>, '<?= h(addslashes($lot['lot_no'])) ?>')" style="padding: 5px 10px; font-size: 0.8rem; min-height: 30px; min-width: 56px;">Delete</button>
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
                                    <p>Click the <strong>+ Add Lot</strong> button above or import from Excel.</p>
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

        <div class="pagination" style="margin-top: 20px; display: flex; gap: 10px; justify-content: center; align-items: center;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($filterStatus) ?>&barangay_id=<?= $filterBarangayId ?>" class="btn btn-secondary">Previous</a>
            <?php endif; ?>
            <span style="font-size: 0.9rem; color: var(--text-muted);">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($filterStatus) ?>&barangay_id=<?= $filterBarangayId ?>" class="btn btn-secondary">Next</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Lot Detail Card Modal -->
<style>
#lotCardOverlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    align-items: center;
    justify-content: center;
    padding: 16px;
}
#lotCardOverlay.open { display: flex; }
#lotCard {
    background: linear-gradient(145deg, rgba(15,24,20,0.98) 0%, rgba(10,20,15,0.98) 100%);
    border: 1px solid rgba(16,185,129,0.3);
    border-radius: 24px;
    width: 100%;
    max-width: 720px;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 0 0 1px rgba(16,185,129,0.1), 0 30px 80px rgba(0,0,0,0.7), 0 0 60px rgba(16,185,129,0.05);
    overflow: hidden;
    animation: cardIn 0.25s cubic-bezier(0.175,0.885,0.32,1.275) forwards;
}
@keyframes cardIn {
    from { opacity:0; transform: scale(0.92) translateY(20px); }
    to   { opacity:1; transform: scale(1)   translateY(0); }
}
.lc-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid rgba(16,185,129,0.2);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    background: linear-gradient(90deg, rgba(16,185,129,0.08) 0%, transparent 70%);
}
.lc-title-block { flex:1; min-width:0; }
.lc-lot-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    font-size: 1.3rem;
    font-weight: 800;
    padding: 6px 18px;
    border-radius: 50px;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    box-shadow: 0 4px 15px rgba(16,185,129,0.35);
}
.lc-subtitle { color: rgba(255,255,255,0.55); font-size: 0.85rem; }
.lc-close-btn {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 50%;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #fff;
    font-size: 1rem;
    flex-shrink: 0;
    transition: background 0.2s;
}
.lc-close-btn:hover { background: rgba(239,68,68,0.25); border-color: rgba(239,68,68,0.4); }
.lc-tabs {
    display: flex;
    gap: 4px;
    padding: 14px 24px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    overflow-x: auto;
}
.lc-tab {
    padding: 8px 16px;
    border-radius: 10px 10px 0 0;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.lc-tab:hover { color: rgba(255,255,255,0.85); }
.lc-tab.active {
    color: #10b981;
    border-bottom-color: #10b981;
    background: rgba(16,185,129,0.06);
}
.lc-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    color: #fff;
}
.lc-pane { display: none; }
.lc-pane.active { display: block; }
.lc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}
.lc-field {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 12px;
    padding: 12px 14px;
}
.lc-field.wide { grid-column: 1 / -1; }
.lc-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #10b981;
    display: block;
    margin-bottom: 4px;
}
.lc-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: #f0fdf4;
    word-break: break-word;
}
.lc-value.muted { color: rgba(255,255,255,0.35); font-style: italic; }
.lc-value.big { font-size: 1.15rem; font-weight: 700; color: #6ee7b7; }
.lc-value.purple { color: #c4b5fd; }
.lc-value.red { color: #fca5a5; }
.lc-footer {
    padding: 14px 24px;
    border-top: 1px solid rgba(255,255,255,0.07);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: rgba(0,0,0,0.2);
}
.lot-row { cursor: pointer; }
.lot-row:hover td { background: rgba(16,185,129,0.05); }
</style>

<div id="lotCardOverlay" onclick="if(event.target===this)closeLotCard()">
    <div id="lotCard">
        <div class="lc-header">
            <div class="lc-title-block">
                <div class="lc-lot-badge">📋 <span id="lc-lot-no"></span></div>
                <div class="lc-subtitle" id="lc-subtitle"></div>
            </div>
            <button class="lc-close-btn" onclick="closeLotCard()" title="Close">✕</button>
        </div>
        <div class="lc-tabs">
            <button class="lc-tab active" onclick="switchLcTab('specs',this)">📐 Specs</button>
            <button class="lc-tab" onclick="switchLcTab('claimants',this)">👤 Claimants</button>
            <button class="lc-tab" onclick="switchLcTab('legal',this)">⚖️ Legal</button>
        </div>
        <div class="lc-body">
            <!-- Specs -->
            <div class="lc-pane active" id="lc-pane-specs">
                <div class="lc-grid">
                    <div class="lc-field">
                        <span class="lc-label">Lot Number</span>
                        <div class="lc-value big" id="lc-s-lot"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Survey No.</span>
                        <div class="lc-value" id="lc-s-survey"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Barangay</span>
                        <div class="lc-value" id="lc-s-brgy"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Area (sqm)</span>
                        <div class="lc-value big" id="lc-s-area"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Status</span>
                        <div id="lc-s-status"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Dominant Use</span>
                        <div class="lc-value" id="lc-s-use"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Subdivision</span>
                        <div class="lc-value" id="lc-s-sub"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Source Sheet</span>
                        <div class="lc-value purple" id="lc-s-sheet"></div>
                    </div>
                </div>
            </div>
            <!-- Claimants -->
            <div class="lc-pane" id="lc-pane-claimants">
                <div class="lc-grid">
                    <div class="lc-field">
                        <span class="lc-label">Current Claimant</span>
                        <div class="lc-value" id="lc-c-current"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Sex (GAD)</span>
                        <div class="lc-value" id="lc-c-sex"></div>
                    </div>
                    <div class="lc-field wide">
                        <span class="lc-label">Current Address</span>
                        <div class="lc-value" id="lc-c-addr"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Survey Claimant</span>
                        <div class="lc-value" id="lc-c-survey"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Tax Declarant</span>
                        <div class="lc-value" id="lc-c-tax"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Representative</span>
                        <div class="lc-value" id="lc-c-rep"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Rep. Address</span>
                        <div class="lc-value" id="lc-c-repaddr"></div>
                    </div>
                </div>
            </div>
            <!-- Legal -->
            <div class="lc-pane" id="lc-pane-legal">
                <div class="lc-grid">
                    <div class="lc-field">
                        <span class="lc-label">Mode of Acquisition</span>
                        <div class="lc-value" id="lc-l-acq"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Titling Interest</span>
                        <div class="lc-value" id="lc-l-int"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Land Case</span>
                        <div class="lc-value" id="lc-l-case"></div>
                    </div>
                    <div class="lc-field">
                        <span class="lc-label">Case Reference</span>
                        <div class="lc-value red" id="lc-l-ref"></div>
                    </div>
                    <div class="lc-field wide">
                        <span class="lc-label">Remarks</span>
                        <div class="lc-value" id="lc-l-remarks" style="white-space:pre-wrap;line-height:1.5;"></div>
                    </div>
                    <div class="lc-field wide" id="lc-l-docs-wrap">
                        <span class="lc-label">Documents</span>
                        <div id="lc-l-docs" style="margin-top:6px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="lc-footer">
            <?php if ($currentUserRole !== 'Viewer'): ?>
            <a id="lc-edit-btn" href="#" class="btn btn-primary" style="padding:8px 18px;min-height:auto;font-size:0.85rem;">✏️ Edit Lot</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" onclick="closeLotCard()" style="padding:8px 18px;min-height:auto;font-size:0.85rem;">Close</button>
        </div>
    </div>
</div>

<script>
// ── Server-generated lot data — keyed by lot ID ──────────────────────────────
<?php
$lotsById = [];
foreach ($lots as $lot) { $lotsById[(int)$lot['id']] = $lot; }
?>
const LOT_DATA = <?= json_encode($lotsById, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const APP_URL  = <?= json_encode(rtrim(app_url(), '/'), JSON_UNESCAPED_SLASHES) ?>;

function toggleAddForm() {
    const panel = document.getElementById('addLotFormPanel');
    const btn = document.getElementById('toggleFormBtn');
    const isOpen = panel.style.display !== 'none';
    if (isOpen) {
        panel.style.display = 'none';
        if (btn) btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Add Lot';
    } else {
        panel.style.display = 'block';
        if (btn) btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg> Hide Form';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function filterLots() {
    // Deprecated: Filtering is now server-side.
}

// ── Card View Functions ───────────────────────────────────────────────────────
function fill(id, val, fallback) {
    const el = document.getElementById(id);
    if (!el) return;
    const v = (val || '').toString().trim();
    el.textContent = v || (fallback !== undefined ? fallback : '—');
    if (!v) el.classList.add('muted'); else el.classList.remove('muted');
}

function openViewById(id) {
    const d = LOT_DATA[id];
    if (!d) { console.warn('Lot ID not found:', id); return; }

    // Header
    document.getElementById('lc-lot-no').textContent = 'Lot ' + (d.lot_no || id);
    document.getElementById('lc-subtitle').textContent =
        (d.barangay_name || '') + (d.survey_no ? ' · ' + d.survey_no : '');

    // Specs tab
    fill('lc-s-lot',    d.lot_no);
    fill('lc-s-survey', d.survey_no);
    fill('lc-s-brgy',   d.barangay_name);
    const area = parseFloat(d.area_sqm) || 0;
    document.getElementById('lc-s-area').textContent =
        area.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' sqm';
    fill('lc-s-use',    d.dominant_use);
    fill('lc-s-sub',    d.subdivision);
    fill('lc-s-sheet',  d.source_sheet);

    // Status badge in specs
    const statusBadges = {
        'Titled':   { bg:'#10b981', icon:'✅' },
        'Applied':  { bg:'#3b82f6', icon:'📄' },
        'Conflict': { bg:'#ef4444', icon:'⚠️' },
        'Unapplied':{ bg:'#f59e0b', icon:'🔍' }
    };
    const sb = statusBadges[d.status] || { bg:'#6b7280', icon:'📌' };
    const statusLabel = d.status === 'Conflict' ? 'With Conflict' : (d.status || 'Unknown');
    document.getElementById('lc-s-status').innerHTML =
        '<span style="display:inline-flex;align-items:center;gap:5px;background:' + sb.bg +
        '22;border:1px solid ' + sb.bg + '55;color:' + sb.bg +
        ';padding:4px 12px;border-radius:20px;font-size:0.82rem;font-weight:700;">' +
        sb.icon + ' ' + statusLabel + '</span>';

    // Claimants tab
    fill('lc-c-current',  d.current_claimant);
    fill('lc-c-sex',      d.claimant_sex);
    fill('lc-c-addr',     d.current_address);
    fill('lc-c-survey',   d.survey_claimant);
    fill('lc-c-tax',      d.tax_declarant);
    fill('lc-c-rep',      d.representative);
    fill('lc-c-repaddr',  d.representative_address);

    // Legal tab
    fill('lc-l-acq',     d.mode_of_acquisition);
    fill('lc-l-int',     d.titling_interest);
    fill('lc-l-case',    d.land_case);
    fill('lc-l-ref',     d.case_reference);
    fill('lc-l-remarks', d.remarks, 'No remarks recorded.');

    // Documents
    let docsHtml = '';
    if (d.supporting_docs) {
        const fn = d.supporting_docs.split('/').pop();
        docsHtml += '<a href="' + APP_URL + '/' + d.supporting_docs + '" target="_blank" class="btn btn-secondary" style="font-size:0.8rem;padding:5px 10px;min-height:auto;margin-right:8px;">📥 Document (' + fn + ')</a>';
    }
    if (d.approved_survey_plan) {
        const fn = d.approved_survey_plan.split('/').pop();
        docsHtml += '<a href="' + APP_URL + '/' + d.approved_survey_plan + '" target="_blank" class="btn btn-secondary" style="font-size:0.8rem;padding:5px 10px;min-height:auto;">🗺️ Survey Plan (' + fn + ')</a>';
    }
    document.getElementById('lc-l-docs').innerHTML = docsHtml ||
        '<span style="color:rgba(255,255,255,0.35);font-size:0.85rem;font-style:italic;">No documents uploaded</span>';

    // Edit button
    const editBtn = document.getElementById('lc-edit-btn');
    if (editBtn) editBtn.href = APP_URL + '/lots/edit.php?id=' + d.id;

    // Open card
    switchLcTab('specs', document.querySelector('.lc-tab'));
    const overlay = document.getElementById('lotCardOverlay');
    // Re-trigger animation
    const card = document.getElementById('lotCard');
    card.style.animation = 'none';
    card.offsetHeight; // reflow
    card.style.animation = '';
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function openViewModalFromRow(row, event) {
    if (event.target.closest('button') || event.target.closest('a')
        || event.target.closest('.confirm-row') || event.target.closest('.confirm-message')) {
        return;
    }
    const id = parseInt(row.dataset.id, 10);
    if (id) openViewById(id);
}

function closeLotCard() {
    document.getElementById('lotCardOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function switchLcTab(tabId, btn) {
    document.querySelectorAll('.lc-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.lc-tab').forEach(b => b.classList.remove('active'));
    const pane = document.getElementById('lc-pane-' + tabId);
    if (pane) pane.classList.add('active');
    if (btn) btn.classList.add('active');
}

function exportLotsCSV() {
    const rows = document.querySelectorAll('#lotsBody .lot-row');
    let csv = 'Lot No,Survey No,Barangay,Claimant,Area (sqm),Dominant Use,Status\n';
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const c = row.querySelectorAll('td');
        csv += `"${c[0].textContent.trim()}","${c[1].textContent.trim()}","${c[2].textContent.trim()}","${c[3].textContent.trim()}","${c[4].textContent.trim()}","${c[5].textContent.trim()}","${c[6].textContent.trim()}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'lots_export.csv'; a.click();
}

function showConfirm(id, lotNo) {
    document.querySelectorAll('.confirm-row').forEach(r => r.classList.remove('active'));
    document.getElementById('confirm-label-' + id).textContent = '"' + lotNo + '"';
    document.getElementById('confirm-' + id).classList.add('active');
}

function hideConfirm(id) {
    document.getElementById('confirm-' + id).classList.remove('active');
}

document.addEventListener('DOMContentLoaded', () => {
    // Move card overlay to <body> so it escapes the .app-shell stacking context
    // (same technique used by the logo modal — see includes/header.php)
    document.body.appendChild(document.getElementById('lotCardOverlay'));

    // Filtering is now handled server-side
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeLotCard();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
