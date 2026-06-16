<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login
require_login();

$pageTitle = 'Land Claims & Disputes';
$pageDescription = 'Track, monitor, and resolve conflicting land claims and active land cases.';

$currentUserRole = get_current_user_role();

// Fetch all lots with 'Conflict' status
$conflictsResult = $mysqli->query("
    SELECT 
        l.id, l.lot_no, l.survey_no, b.name AS barangay_name, b.id AS barangay_id, l.area_sqm, l.status,
        l.survey_claimant, l.tax_declarant, l.current_claimant, l.claimant_sex, l.current_address,
        l.representative, l.representative_address, l.supporting_docs, l.subdivision, l.approved_survey_plan,
        l.land_case, l.titling_interest, l.mode_of_acquisition, l.dominant_use, l.remarks,
        l.source_sheet, l.case_reference, l.sheet_row
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    WHERE l.status = 'Conflict'
    ORDER BY l.case_reference ASC, l.id DESC
");
$conflicts = $conflictsResult ? $conflictsResult->fetch_all(MYSQLI_ASSOC) : [];

// Compute stat values
$totalConflicts   = count($conflicts);
$uniqueBarangays  = count(array_unique(array_column($conflicts, 'barangay_name')));
$totalDisputeArea = array_sum(array_column($conflicts, 'area_sqm'));
$uniqueCaseRefs   = count(array_filter(array_unique(array_column($conflicts, 'case_reference'))));

// Build barangay list for filter
$conflictBrgyList = [];
foreach ($conflicts as $c) {
    $conflictBrgyList[$c['barangay_id']] = $c['barangay_name'];
}

require_once __DIR__ . '/../includes/header.php';
?>


<!-- Stat Cards -->
<div class="conflict-stats-grid">
    <div class="conflict-stat-card cs-danger">
        <div class="cs-label">Active Disputes</div>
        <div class="cs-value"><?= $totalConflicts; ?></div>
    </div>
    <div class="conflict-stat-card cs-warning">
        <div class="cs-label">Barangays Affected</div>
        <div class="cs-value"><?= $uniqueBarangays; ?></div>
    </div>
    <div class="conflict-stat-card">
        <div class="cs-label">Total Disputed Area</div>
        <div class="cs-value" style="font-size: 1.1rem; padding-top: 6px;"><?= format_number($totalDisputeArea); ?> <small style="font-size: 0.6em; color: var(--text-muted);">sqm</small></div>
    </div>
    <div class="conflict-stat-card cs-primary">
        <div class="cs-label">Case References</div>
        <div class="cs-value"><?= $uniqueCaseRefs; ?></div>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="filter-toolbar" style="margin-bottom: 20px;">
    <input type="text" id="conflictFilterText" placeholder="🔍 Search by Case Reference, Claimant, Lot, or Survey..." oninput="filterConflicts()">
    <select id="conflictFilterBrgy" onchange="filterConflicts()" style="max-width: 220px;">
        <option value="">All Barangays</option>
        <?php foreach ($conflictBrgyList as $bId => $bName): ?>
            <option value="<?= h($bName); ?>"><?= h($bName); ?></option>
        <?php endforeach; ?>
    </select>
    <span class="table-counter" id="conflictCounter"><?= $totalConflicts; ?> active disputes</span>
    <?php if ($conflicts): ?>
        <button class="btn btn-export" onclick="exportConflictsCSV()" style="padding: 7px 14px; font-size: 0.82rem; min-height: 34px;">⬇ Export CSV</button>
    <?php endif; ?>
</div>

<section class="panel">
    <div class="actions-spread" style="margin-bottom: 15px;">
        <h2 class="panel-title" style="margin: 0;">Disputed Lands Directory</h2>
    </div>

    <div class="table-wrap">
        <table class="table-sticky" id="conflictsTable">
            <thead>
                <tr>
                    <th>Case Ref</th>
                    <th>Lot No</th>
                    <th>Survey No</th>
                    <th>Barangay</th>
                    <th>Current Claimant</th>
                    <th>Land Case Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="conflictsBody">
                <?php if ($conflicts): ?>
                    <?php foreach ($conflicts as $lot): ?>
                        <tr class="conflict-row" onclick="openViewModalFromRow(this, event)"
                            data-caseref="<?= h(strtolower($lot['case_reference'] ?? '')) ?>"
                            data-lot="<?= h(strtolower($lot['lot_no'])) ?>"
                            data-survey="<?= h(strtolower($lot['survey_no'])) ?>"
                            data-barangay="<?= h(strtolower($lot['barangay_name'])) ?>"
                            data-claimant="<?= h(strtolower($lot['current_claimant'] ?? $lot['survey_claimant'] ?? '')) ?>"
                            data-details='<?= h(json_encode($lot)); ?>'>
                            <td>
                                <strong style="color: var(--danger);"><?= h($lot['case_reference'] ?: 'No Case Reference'); ?></strong>
                            </td>
                            <td><strong><?= h($lot['lot_no']); ?></strong></td>
                            <td><?= h($lot['survey_no']); ?></td>
                            <td><?= h($lot['barangay_name']); ?></td>
                            <td><?= h($lot['current_claimant'] ?: $lot['survey_claimant'] ?: '—'); ?></td>
                            <td>
                                <span class="badge red" style="font-size: 0.75rem; padding: 4px 8px;">
                                    ⚠️ <?= h($lot['land_case'] ?: 'Pending Review'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="inline-actions">
                                    <button type="button" class="btn btn-secondary" onclick="openViewModal(this)" style="padding: 8px 12px; font-size: 0.88rem; min-height: 36px;">View Details</button>
                                    <?php if ($currentUserRole !== 'Viewer'): ?>
                                        <a class="btn btn-secondary" href="<?= h(app_url('/lots/edit.php?id=' . $lot['id'])); ?>">Resolve / Edit</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="emptyConflictRow">
                        <td colspan="7">
                            <div class="empty-state-fancy" style="padding: 40px 20px;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                <h3 style="color: var(--success);">No Active Land Conflicts</h3>
                                <p>Great job! There are currently no recorded lot boundary disputes or overlapping land claims.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <!-- Filter empty state (hidden by default) -->
                <tr id="noConflictResultsRow" style="display:none;">
                    <td colspan="7">
                        <div class="empty-state-fancy">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <h3>No Matching Conflict Cases</h3>
                            <p>Try searching for a different case number or claimant name.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Detailed View Modal -->
<div id="viewModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: var(--glass-blur);">
    <div class="modal-card" style="background: rgba(18, 27, 22, 0.95); border: 1px solid var(--panel-border); border-radius: 20px; width: 90%; max-width: 750px; box-shadow: 0 20px 50px rgba(0,0,0,0.6); overflow: hidden; display: flex; flex-direction: column; max-height: 85vh;">
        <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--panel-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #fff; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                ⚠️ Dispute Case File: <span id="m-title-case-ref" style="color: var(--danger);"></span>
            </h3>
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()" style="min-height: auto; padding: 6px 12px; font-size: 0.85rem;">Close</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; color: #fff;">
            <!-- Tabs in modal -->
            <div class="modal-tabs" style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--panel-border); padding-bottom: 10px; overflow-x: auto; white-space: nowrap;">
                <button type="button" id="modal-tab-btn-basic" class="btn btn-primary" onclick="switchModalTab('basic')" style="padding: 6px 12px; min-height: auto; font-size: 0.85rem; flex-shrink: 0;">Technical Specs</button>
                <button type="button" id="modal-tab-btn-claimant" class="btn btn-secondary" onclick="switchModalTab('claimant')" style="padding: 6px 12px; min-height: auto; font-size: 0.85rem; flex-shrink: 0;">Claimants & GAD</button>
                <button type="button" id="modal-tab-btn-legal" class="btn btn-secondary" onclick="switchModalTab('legal')" style="padding: 6px 12px; min-height: auto; font-size: 0.85rem; flex-shrink: 0;">Case & Documentation</button>
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
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Current Claimant (Disputant)</span><strong id="m-current-claimant" style="font-size: 1.05rem; color: var(--danger);"></strong></div>
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
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Case Status</span><span id="m-land-case" style="font-weight: 700; color: var(--danger);"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Case Reference Code</span><span id="m-case-ref" style="font-weight: 700; color: #fff;"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Mode of Acquisition</span><span id="m-acquisition"></span></div>
                    <div><span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Titling Interest</span><span id="m-interest"></span></div>
                    
                    <div style="grid-column: span 2;">
                        <span style="color: var(--text-muted); font-size: 0.82rem; display: block;">Case History / Investigation Remarks</span>
                        <p id="m-remarks" style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 10px; margin-top: 6px; white-space: pre-wrap; font-size: 0.92rem; line-height: 1.5; border: 1px solid var(--panel-border);"></p>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; display: flex; flex-direction: column; justify-content: center; min-height: 80px;">
                        <strong style="font-size: 0.88rem; display: block; margin-bottom: 6px;">📁 Supporting Documents:</strong>
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
                <a id="m-resolve-btn" href="#" class="btn btn-primary" style="padding: 8px 16px; min-height: auto; font-size: 0.85rem;">Resolve / Edit Case</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()" style="padding: 8px 16px; min-height: auto; font-size: 0.85rem;">Close</button>
        </div>
    </div>
</div>

<script>
function filterConflicts() {
    const text = document.getElementById('conflictFilterText').value.toLowerCase();
    const brgy = document.getElementById('conflictFilterBrgy').value.toLowerCase();
    const rows = document.querySelectorAll('#conflictsBody .conflict-row');
    const noResults = document.getElementById('noConflictResultsRow');
    let visible = 0;

    rows.forEach(row => {
        const textMatch = row.dataset.caseref.includes(text)
            || row.dataset.lot.includes(text)
            || row.dataset.survey.includes(text)
            || row.dataset.barangay.includes(text)
            || row.dataset.claimant.includes(text);
        const brgyMatch = !brgy || row.dataset.barangay === brgy;

        const show = textMatch && brgyMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    document.getElementById('conflictCounter').textContent = visible + ' active disputes';
}

function exportConflictsCSV() {
    const rows = document.querySelectorAll('#conflictsBody .conflict-row');
    let csv = 'Case Reference,Lot No,Survey No,Barangay,Current Claimant,Land Case Status\n';
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const c = row.querySelectorAll('td');
        csv += `"${c[0].textContent.trim()}","${c[1].textContent.trim()}","${c[2].textContent.trim()}","${c[3].textContent.trim()}","${c[4].textContent.trim()}","${c[5].textContent.trim()}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'land_conflicts.csv'; a.click();
}


// Modal View Functions
function openViewModal(element) {
    const row = element.tagName === 'TR' ? element : element.closest('tr');
    const details = JSON.parse(row.dataset.details);
    const appUrlBase = '<?= h(app_url()); ?>';

    // Populate fields
    document.getElementById('m-title-case-ref').textContent = details.case_reference || 'Dispute File';
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
    
    document.getElementById('m-acquisition').textContent = details.mode_of_acquisition || '—';
    document.getElementById('m-interest').textContent = details.titling_interest || '—';
    document.getElementById('m-land-case').textContent = details.land_case || 'Pending Review';
    document.getElementById('m-case-ref').textContent = details.case_reference || 'No Reference Code';
    document.getElementById('m-remarks').textContent = details.remarks || 'No detailed case investigation log recorded for this conflict.';

    // Documents
    const docLinkDiv = document.getElementById('m-doc-link');
    if (details.supporting_docs) {
        const fileBase = details.supporting_docs.split('/').pop();
        docLinkDiv.innerHTML = `<a href="${appUrlBase}/${details.supporting_docs}" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 10px; min-height:auto;">📥 Download Case Doc (${fileBase})</a>`;
    } else {
        docLinkDiv.innerHTML = '<span style="color: var(--text-muted); font-size:0.85rem;">No files uploaded</span>';
    }

    const planLinkDiv = document.getElementById('m-plan-link');
    if (details.approved_survey_plan) {
        const fileBase = details.approved_survey_plan.split('/').pop();
        planLinkDiv.innerHTML = `<a href="${appUrlBase}/${details.approved_survey_plan}" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 10px; min-height:auto;">📥 Download Survey Map (${fileBase})</a>`;
    } else {
        planLinkDiv.innerHTML = '<span style="color: var(--text-muted); font-size:0.85rem;">No map uploaded</span>';
    }

    // Set Resolve button url
    const resolveBtn = document.getElementById('m-resolve-btn');
    if (resolveBtn) {
        resolveBtn.href = `${appUrlBase}/lots/edit.php?id=${details.id}`;
    }

    // Display modal
    switchModalTab('basic');
    document.getElementById('viewModal').style.display = 'flex';
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function openViewModalFromRow(row, event) {
    if (event.target.closest('button') || event.target.closest('a')) {
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
