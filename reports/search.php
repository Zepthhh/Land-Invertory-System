<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$pageTitle = 'Search Records';
$pageDescription = 'Search lot records by lot number, survey number, claimant name, or barangay.';

$query        = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$municipalityFilter = (int)($_GET['municipality_id'] ?? 0);
$barangayFilter = (int)($_GET['barangay_id'] ?? 0);
$results      = [];
$searchSummary = null;

$municipalitiesRes = $mysqli->query("SELECT id, name FROM municipality ORDER BY name ASC");
$municipalities = $municipalitiesRes ? $municipalitiesRes->fetch_all(MYSQLI_ASSOC) : [];
$barangays = fetch_barangays($mysqli);

$hasSearch = ($query !== '' || $statusFilter !== '' || $municipalityFilter > 0 || $barangayFilter > 0);

if ($hasSearch) {
    $conditions = [];
    $params     = [];
    $types      = '';

    if ($query !== '') {
        $like = '%' . $query . '%';
        $conditions[] = "(l.lot_no LIKE ? OR l.survey_no LIKE ? OR l.current_claimant LIKE ? OR l.survey_claimant LIKE ? OR l.tax_declarant LIKE ? OR b.name LIKE ? OR l.status LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
        $types .= 'sssssss';
    }

    if ($statusFilter !== '' && in_array($statusFilter, lot_statuses(), true)) {
        $conditions[] = "l.status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }

    if ($municipalityFilter > 0) {
        $conditions[] = "b.municipality_id = ?";
        $params[] = $municipalityFilter;
        $types .= 'i';
    }

    if ($barangayFilter > 0) {
        $conditions[] = "l.barangay_id = ?";
        $params[] = $barangayFilter;
        $types .= 'i';
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "
        SELECT l.id, l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status,
               l.current_claimant, l.survey_claimant, l.tax_declarant, l.dominant_use, l.case_reference
        FROM lots l
        INNER JOIN barangay b ON b.id = l.barangay_id
        $where
        ORDER BY l.id DESC
        LIMIT 500
    ";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Summary stats
    $sqlSummary = "
        SELECT COUNT(*) AS total_lots, COALESCE(SUM(l.area_sqm), 0) AS total_area_sqm,
               COUNT(DISTINCT l.barangay_id) AS total_barangays
        FROM lots l
        INNER JOIN barangay b ON b.id = l.barangay_id
        $where
    ";
    $stmtS = $mysqli->prepare($sqlSummary);
    if ($stmtS) {
        if ($params) {
            $stmtS->bind_param($types, ...$params);
        }
        $stmtS->execute();
        $searchSummary = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search Bar -->
<section class="panel" style="padding: 24px;">
    <form method="get" action="<?= h(app_url('/reports/search.php')); ?>">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
            <div style="grid-column: 1 / -1;">
                <label for="q" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; display: block; font-weight: 600;">Keywords</label>
                <input type="text" id="q" name="q" value="<?= h($query); ?>"
                    placeholder="Search by lot no, survey no, claimant name, or tax declarant..."
                    style="width: 100%; padding: 12px 18px; border-radius: 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); color: #fff; font-size: 1rem; font-family: inherit;">
            </div>
            
            <div>
                <label for="status" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; display: block; font-weight: 600;">Status</label>
                <select id="status" name="status" style="width: 100%; padding: 12px 18px; border-radius: 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); color: #fff; font-size: 0.95rem; font-family: inherit;">
                    <option value="">All Statuses</option>
                    <?php foreach (lot_statuses() as $s): ?>
                        <option value="<?= h($s); ?>" <?= $statusFilter === $s ? 'selected' : ''; ?>><?= h(get_status_label($s)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="municipality_id" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; display: block; font-weight: 600;">Municipality</label>
                <select id="munFilter" name="municipality_id" onchange="filterBarangayDropdown()" style="width: 100%; padding: 12px 18px; border-radius: 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); color: #fff; font-size: 0.95rem; font-family: inherit;">
                    <option value="">All Municipalities</option>
                    <?php foreach ($municipalities as $m): ?>
                        <option value="<?= h((string)$m['id']); ?>" <?= $municipalityFilter === (int)$m['id'] ? 'selected' : ''; ?>><?= h($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="barangay_id" style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; display: block; font-weight: 600;">Barangay</label>
                <select id="brgyFilter" name="barangay_id" style="width: 100%; padding: 12px 18px; border-radius: 12px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.15); color: #fff; font-size: 0.95rem; font-family: inherit;">
                    <option value="">All Barangays</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= h((string)$b['id']); ?>" data-mun-id="<?= h((string)$b['municipality_id']); ?>" <?= $barangayFilter === (int)$b['id'] ? 'selected' : ''; ?>><?= h($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 10px;">
                <button class="btn btn-primary" type="submit" style="min-height: 48px; padding: 12px 24px; font-weight: 600; flex: 1;">Search Records</button>
                <?php if ($hasSearch): ?>
                    <a class="btn btn-secondary" href="<?= h(app_url('/reports/search.php')); ?>" style="min-height: 48px; padding: 12px 24px; font-weight: 600;">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</section>

<script>
function filterBarangayDropdown() {
    const munId = document.getElementById('munFilter').value;
    const brgySelect = document.getElementById('brgyFilter');
    
    Array.from(brgySelect.options).forEach(opt => {
        if (opt.value === "") return; 
        const optMunId = opt.getAttribute('data-mun-id');
        const show = !munId || optMunId === munId;
        opt.style.display = show ? '' : 'none';
        
        if (opt.selected && !show) {
            brgySelect.value = "";
        }
    });
}
document.addEventListener('DOMContentLoaded', filterBarangayDropdown);
</script>

<!-- Summary Cards (only when searched) -->
<?php if ($hasSearch && $searchSummary): ?>
<section class="cards" style="margin-bottom: 20px;">
    <div class="card">
        <div class="card-label">Matching Lots</div>
        <div class="card-value"><?= h((string) $searchSummary['total_lots']); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Total Area (sqm)</div>
        <div class="card-value" style="font-size: 1.3rem;"><?= h(format_number((float) $searchSummary['total_area_sqm'])); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Barangays Covered</div>
        <div class="card-value"><?= h((string) $searchSummary['total_barangays']); ?></div>
    </div>
</section>
<?php endif; ?>

<!-- Results Table -->
<section class="panel">
    <div class="actions-spread" style="margin-bottom: 15px;">
        <h2 class="panel-title" style="margin:0;">
            <?php if (!$hasSearch): ?>Search Results<?php else: ?><?= count($results); ?> Result<?= count($results) !== 1 ? 's' : ''; ?> Found<?php endif; ?>
        </h2>
        <?php if ($results): ?>
            <button class="btn btn-export" onclick="exportSearchCSV()" style="padding: 7px 14px; font-size: 0.82rem; min-height: 34px;">⬇ Export CSV</button>
        <?php endif; ?>
    </div>

    <?php if (!$hasSearch): ?>
        <div class="empty-state-fancy" style="padding: 50px 20px;">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity: 0.3; margin-bottom: 15px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <h3>Start a Search</h3>
            <p>Enter keywords, select a status, or choose a barangay above to find lot records.</p>
        </div>
    <?php elseif (!$results): ?>
        <div class="empty-state-fancy" style="padding: 40px 20px;">
            <h3>No Results Found</h3>
            <p>Try different keywords or adjust the filters.</p>
        </div>
    <?php else: ?>
        <!-- Client-side filter -->
        <div class="filter-toolbar" style="margin-bottom: 12px;">
            <input type="text" id="resultFilter" placeholder="🔍 Filter results..." oninput="filterResults()">
            <span class="table-counter" id="resultCounter"><?= count($results); ?> records</span>
        </div>

        <div class="table-wrap">
            <table class="table-compact table-sticky" id="searchResultTable">
                <thead>
                    <tr>
                        <th>Lot No</th>
                        <th>Survey No</th>
                        <th>Barangay</th>
                        <th>Claimant</th>
                        <th>Dominant Use</th>
                        <th>Area (sqm)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="searchResultBody">
                    <?php foreach ($results as $row): ?>
                        <tr class="search-result-row"
                            data-text="<?= h(strtolower(implode(' ', [
                                $row['lot_no'], $row['survey_no'], $row['barangay_name'],
                                $row['current_claimant'] ?? '', $row['survey_claimant'] ?? '',
                                $row['tax_declarant'] ?? '', $row['case_reference'] ?? '',
                            ]))); ?>">
                            <td><strong><?= h($row['lot_no']); ?></strong></td>
                            <td><?= h($row['survey_no']); ?></td>
                            <td><?= h($row['barangay_name']); ?></td>
                            <td><?= h($row['current_claimant'] ?: ($row['survey_claimant'] ?: ($row['tax_declarant'] ?: '—'))); ?></td>
                            <td><?= h($row['dominant_use'] ?: '—'); ?></td>
                            <td><?= format_number((float) $row['area_sqm']); ?></td>
                            <td>
                                <span class="<?= h(get_status_badge_class($row['status'])); ?>">
                                    <?= get_status_icon($row['status']); ?> <?= h(get_status_label($row['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="inline-actions">
                                    <a class="btn btn-secondary" href="<?= h(app_url('/lots/edit.php?id=' . $row['id'])); ?>" style="padding: 6px 12px; font-size: 0.8rem; min-height: 30px;">Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
function filterResults() {
    const text = document.getElementById('resultFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#searchResultBody .search-result-row');
    let visible = 0;
    rows.forEach(row => {
        const match = row.dataset.text.includes(text);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('resultCounter').textContent = visible + ' records';
}

function exportSearchCSV() {
    const rows = document.querySelectorAll('#searchResultBody .search-result-row');
    let csv = 'Lot No,Survey No,Barangay,Claimant,Dominant Use,Area (sqm),Status\n';
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const c = row.querySelectorAll('td');
        csv += `"${c[0].textContent.trim()}","${c[1].textContent.trim()}","${c[2].textContent.trim()}","${c[3].textContent.trim()}","${c[4].textContent.trim()}","${c[5].textContent.trim()}","${c[6].textContent.trim()}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'search_results.csv'; a.click();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
