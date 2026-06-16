<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login
require_login();

$pageTitle = 'Reports';
$pageDescription = 'Barangay totals, GAD claimant statistics, land-use deductions, and survey summaries.';

// Barangay Summary Query
$barangaySummaryResult = $mysqli->query("
    SELECT
        b.name,
        b.total_area_sqm,
        COALESCE(l.total_lots, 0) AS total_lots,
        COALESCE(l.total_lot_area, 0) AS total_lot_area,
        COALESCE(u.infrastructure_area, 0) AS infrastructure_area,
        COALESCE(u.community_area, 0) AS community_area,
        COALESCE(u.total_land_use, 0) AS total_land_use,
        (b.total_area_sqm - COALESCE(u.total_land_use, 0)) AS area_after_land_use,
        ((b.total_area_sqm - COALESCE(u.total_land_use, 0)) - COALESCE(l.total_lot_area, 0)) AS remaining_balance
    FROM barangay b
    LEFT JOIN (
        SELECT barangay_id, COUNT(*) AS total_lots, SUM(area_sqm) AS total_lot_area
        FROM lots
        GROUP BY barangay_id
    ) l ON l.barangay_id = b.id
    LEFT JOIN (
        SELECT
            barangay_id,
            SUM(CASE WHEN type IN ('Alley', 'Road', 'Irrigation', 'Canal') THEN area_sqm ELSE 0 END) AS infrastructure_area,
            SUM(CASE WHEN type IN ('Church', 'School', 'School Site', 'Plaza') THEN area_sqm ELSE 0 END) AS community_area,
            SUM(area_sqm) AS total_land_use
        FROM land_use
        GROUP BY barangay_id
    ) u ON u.barangay_id = b.id
    ORDER BY b.name ASC
");
$barangaySummary = $barangaySummaryResult ? $barangaySummaryResult->fetch_all(MYSQLI_ASSOC) : [];

$wholeArea = 0.0;
foreach ($barangaySummary as $row) {
    $wholeArea += (float) $row['total_area_sqm'];
}

// Survey Total Query
$surveySummaryResult = $mysqli->query("
    SELECT survey_no, COUNT(*) AS total_lots, SUM(area_sqm) AS total_area_sqm
    FROM lots
    GROUP BY survey_no
    ORDER BY total_area_sqm DESC, survey_no ASC
");
$surveySummary = $surveySummaryResult ? $surveySummaryResult->fetch_all(MYSQLI_ASSOC) : [];

// Status Counts Query
$statusCountResult = $mysqli->query("
    SELECT status, COUNT(*) AS total
    FROM lots
    GROUP BY status
    ORDER BY CASE status WHEN 'Unapplied' THEN 1 WHEN 'Applied' THEN 2 WHEN 'Titled' THEN 3 WHEN 'Conflict' THEN 4 ELSE 5 END ASC
");
$statusCounts = $statusCountResult ? $statusCountResult->fetch_all(MYSQLI_ASSOC) : [];

// GAD Gender Distribution Query
$gadResult = $mysqli->query("
    SELECT 
        CASE 
            WHEN claimant_sex = 'F' THEN 'Female'
            WHEN claimant_sex = 'M' THEN 'Male'
            WHEN claimant_sex = 'M/F' THEN 'Co-owners (Both)'
            ELSE 'Unspecified'
        END AS gender,
        COUNT(*) AS total_lots,
        COALESCE(SUM(area_sqm), 0) AS total_area
    FROM lots
    GROUP BY gender
    ORDER BY total_area DESC
");
$gadData = $gadResult ? $gadResult->fetch_all(MYSQLI_ASSOC) : [];

$gadTotalLots = 0;
$gadTotalArea = 0.0;
foreach ($gadData as $gd) {
    $gadTotalLots += (int)$gd['total_lots'];
    $gadTotalArea += (float)$gd['total_area'];
}

// Map parsed results into constant gender list
$gadMap = [];
foreach ($gadData as $gd) {
    $gadMap[$gd['gender']] = $gd;
}
foreach (['Female', 'Male', 'Co-owners (Both)', 'Unspecified'] as $g) {
    if (!isset($gadMap[$g])) {
        $gadMap[$g] = ['gender' => $g, 'total_lots' => 0, 'total_area' => 0.0];
    }
}

// Lots table
$identifiedLotsResult = $mysqli->query("
    SELECT l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.current_claimant, l.survey_claimant, l.status
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    ORDER BY b.name ASC, l.survey_no ASC, CAST(l.lot_no AS INTEGER), l.lot_no ASC
    LIMIT 300
");
$identifiedLots = $identifiedLotsResult ? $identifiedLotsResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="print-header">
    <img src="<?= h(app_url('/assets/img/logo.png')); ?>" alt="DENR Logo">
    <div class="print-header-text">
        <p>Republic of the Philippines</p>
        <p><strong>DEPARTMENT OF ENVIRONMENT AND NATURAL RESOURCES</strong></p>
        <p>CENRO Office Land Database</p>
        <h2>OFFICIAL LAND INVENTORY REPORT</h2>
    </div>
</div>

<section class="cards print-no-show">
    <div class="card">
        <div class="card-label">Whole Area (sqm)</div>
        <div class="card-value"><?= h(format_number($wholeArea)); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Survey Numbers</div>
        <div class="card-value"><?= h((string) count($surveySummary)); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Identified Lots</div>
        <div class="card-value"><?= h((string) count_table_rows($mysqli, 'lots')); ?></div>
    </div>
    <div class="card">
        <div class="card-label">Land Use Deductions (sqm)</div>
        <div class="card-value"><?= h(format_number(sum_table_area($mysqli, 'land_use'))); ?></div>
    </div>
</section>

<div class="actions-spread print-no-show">
    <h2 class="panel-title" style="margin: 0;">Comprehensive Reports</h2>
    <div class="inline-actions">
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Report
        </button>
        <button class="btn btn-export print-no-show" onclick="exportBarangaySummaryCSV()">
            ⬇ Export Summary CSV
        </button>
        <a class="btn btn-secondary" href="<?= h(app_url('/reports/search.php')); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            Search Records
        </a>
        <a class="btn btn-danger" href="<?= h(app_url('/reports/conflicts.php')); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            Land Conflicts
        </a>
    </div>
</div>

<section class="panel">
    <h2 class="panel-title">Barangay Summary</h2>
    <p class="section-text print-no-show">Each barangay shows total square meters, total lots, grouped deductions for `Alley/Road/Irrigation/Canal` and `Church/School Site/Plaza`, then the remaining area after deductions and identified lots.</p>
    <div class="table-wrap">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Whole Area<br><small>(sqm)</small></th>
                    <th>Lots</th>
                    <th>Lot Area<br><small>(sqm)</small></th>
                    <th>Infra. Deductions<br><small>Alley/Road/Canal</small></th>
                    <th>Community Deductions<br><small>Church/School/Plaza</small></th>
                    <th>Total Deductions<br><small>(sqm)</small></th>
                    <th>After Deductions<br><small>(sqm)</small></th>
                    <th>Remaining Balance<br><small>(sqm)</small></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($barangaySummary): ?>
                    <?php foreach ($barangaySummary as $row): ?>
                        <?php
                        $wholeBarangayArea = (float) $row['total_area_sqm'];
                        $areaAfterDeductions = (float) $row['area_after_land_use'];
                        $remainingBalance = (float) $row['remaining_balance'];
                        
                        $utilized = $wholeBarangayArea - $remainingBalance;
                        $percent_utilized = ($wholeBarangayArea > 0) ? ($utilized / $wholeBarangayArea) * 100 : 0;
                        
                        $progress_class = '';
                        if ($percent_utilized > 90) $progress_class = 'danger';
                        elseif ($percent_utilized > 75) $progress_class = 'warning';
                        ?>
                        <tr>
                            <td><strong><?= h($row['name']); ?></strong></td>
                            <td><?= format_number($wholeBarangayArea); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['total_lot_area']); ?></td>
                            <td><?= format_number((float) $row['infrastructure_area']); ?></td>
                            <td><?= format_number((float) $row['community_area']); ?></td>
                            <td><?= format_number((float) $row['total_land_use']); ?></td>
                            <td><?= format_number($areaAfterDeductions); ?></td>
                            <td style="min-width: 150px;">
                                <div style="font-weight: 600; color: <?= $remainingBalance < 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                    <?= format_number($remainingBalance); ?> sqm
                                </div>
                                <div class="progress-container print-no-show">
                                    <div class="progress-bar <?= $progress_class ?>" style="width: <?= min(100, max(0, $percent_utilized)) ?>%;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No report data available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- GENDER AND DEVELOPMENT (GAD) COMPLIANCE SECTION -->
<section class="panel">
    <h2 class="panel-title">Gender & Development (GAD) Land Distribution</h2>
    <p class="section-text print-no-show">Philippine government statistics on land patents and appraisals awarded to Female, Male, and Co-owners.</p>
    
    <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start; margin-top: 15px;">
        <!-- Left: SVG GAD Column Chart -->
        <div style="flex: 1; min-width: 250px;" class="print-no-show">
            <svg viewBox="0 0 160 100" width="100%" height="220" style="background: rgba(0,0,0,0.15); border-radius: 12px; border: 1px solid var(--panel-border); padding: 15px;">
                <!-- Grid lines -->
                <line x1="20" y1="20" x2="150" y2="20" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"></line>
                <line x1="20" y1="50" x2="150" y2="50" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"></line>
                <line x1="20" y1="80" x2="150" y2="80" stroke="rgba(255,255,255,0.05)" stroke-width="0.5"></line>
                
                <?php
                $genderColors = [
                    'Female' => '#ec4899',           // Pink
                    'Male' => '#3b82f6',             // Blue
                    'Co-owners (Both)' => '#10b981', // Green
                    'Unspecified' => '#6b7280'       // Gray
                ];
                
                $xPos = [
                    'Female' => 30,
                    'Male' => 60,
                    'Co-owners (Both)' => 90,
                    'Unspecified' => 120
                ];
                
                foreach ($gadMap as $gName => $gData) {
                    $area = (float)$gData['total_area'];
                    $pct = $gadTotalArea > 0 ? ($area / $gadTotalArea) : 0.0;
                    $barHeight = $pct * 60; // Max height 60
                    $y = 80 - $barHeight;
                    $color = $genderColors[$gName] ?? '#9ca3af';
                    $x = $xPos[$gName] ?? 30;
                    ?>
                    <!-- Bar -->
                    <rect x="<?= $x ?>" y="<?= $y ?>" width="18" height="<?= $barHeight ?>" fill="<?= $color ?>" rx="3" style="transition: height 0.5s ease;"></rect>
                    <!-- Bar label percentage -->
                    <text x="<?= $x + 9 ?>" y="<?= $y - 4 ?>" font-size="6" fill="#fff" text-anchor="middle" font-weight="700">
                        <?= number_format($pct * 100, 1) ?>%
                    </text>
                    <!-- X Axis label -->
                    <text x="<?= $x + 9 ?>" y="88" font-size="5" fill="var(--text-muted)" text-anchor="middle" font-weight="600">
                        <?= h(str_replace(' (Both)', '', $gName)); ?>
                    </text>
                    <?php
                }
                ?>
                <!-- Axis line -->
                <line x1="20" y1="80" x2="150" y2="80" stroke="rgba(255,255,255,0.2)" stroke-width="1"></line>
            </svg>
        </div>

        <!-- Right: Tabular GAD Data -->
        <div style="flex: 1.5; min-width: 300px; width: 100%;">
            <div class="table-wrap">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Claimant Gender</th>
                            <th>Total Lots</th>
                            <th>Total Area (sqm)</th>
                            <th>Area Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gadMap as $gName => $gData): 
                            $area = (float)$gData['total_area'];
                            $pct = $gadTotalArea > 0 ? ($area / $gadTotalArea) * 100 : 0.0;
                            $color = $genderColors[$gName] ?? '#9ca3af';
                        ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="display: inline-block; width: 9px; height: 9px; border-radius: 50%; background-color: <?= $color ?>;"></span>
                                        <strong><?= h($gName); ?></strong>
                                    </div>
                                </td>
                                <td><?= h((string)$gData['total_lots']); ?></td>
                                <td><?= format_number($area); ?></td>
                                <td><?= format_percent($pct); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="grid-2">
    <div class="panel">
        <h2 class="panel-title">Survey Total Per Survey Number</h2>
        <div class="table-wrap">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>Survey No</th>
                        <th>Lots</th>
                        <th>Total Area<br><small>(sqm)</small></th>
                        <th>Share %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($surveySummary): ?>
                        <?php foreach ($surveySummary as $row): ?>
                            <?php
                            $surveyArea = (float) $row['total_area_sqm'];
                            $wholeShare = $wholeArea > 0 ? ($surveyArea / $wholeArea) * 100 : 0.0;
                            ?>
                            <tr>
                                <td><?= h($row['survey_no']); ?></td>
                                <td><?= h((string) $row['total_lots']); ?></td>
                                <td><?= format_number($surveyArea); ?></td>
                                <td><?= format_percent($wholeShare); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No survey records available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2 class="panel-title">Status Count</h2>
        <div class="table-wrap">
            <table class="table-compact">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Total Lots</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($statusCounts): ?>
                        <?php foreach ($statusCounts as $row): ?>
                            <tr>
                                <td>
                                    <span class="<?= h(get_status_badge_class($row['status'])); ?>">
                                        <?= get_status_icon($row['status']); ?> <?= h(get_status_label($row['status'])); ?>
                                    </span>
                                </td>
                                <td><?= h((string) $row['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">No status records available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="actions-spread print-no-show" style="margin-bottom: 14px;">
        <div>
            <h2 class="panel-title" style="margin:0;">Identify Lot and Area (sqm)</h2>
            <p class="section-text" style="margin-top: 6px; margin-bottom: 0;">Lists identified lots with survey number, claimant, area, and status (up to 300 entries).</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span class="table-counter" id="lotsReportCounter"><?= count($identifiedLots); ?> lots</span>
            <?php if ($identifiedLots): ?>
                <button class="btn btn-export" onclick="exportLotsCSV()" style="padding: 7px 14px; font-size: 0.82rem; min-height: 34px;">⬇ Export CSV</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter bar for identified lots -->
    <div class="filter-toolbar print-no-show">
        <input type="text" id="lotsReportFilter" placeholder="🔍 Filter by lot no, survey, barangay, claimant..." oninput="filterLotsReport()">
        <select id="lotsReportStatusFilter" onchange="filterLotsReport()">
            <option value="">All Statuses</option>
            <?php foreach (lot_statuses() as $s): ?>
                <option value="<?= h($s); ?>"><?= h(get_status_label($s)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-wrap">
        <table class="table-compact table-sticky" id="lotsReportTable">
            <thead>
                <tr>
                    <th>Lot No</th>
                    <th>Survey No</th>
                    <th>Barangay</th>
                    <th>Claimant</th>
                    <th>Area (sqm)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="lotsReportBody">
                <?php if ($identifiedLots): ?>
                    <?php foreach ($identifiedLots as $row): ?>
                        <tr class="lots-report-row"
                            data-text="<?= h(strtolower(implode(' ', [$row['lot_no'], $row['survey_no'], $row['barangay_name'], $row['current_claimant'] ?? '', $row['survey_claimant'] ?? '']))); ?>"
                            data-status="<?= h($row['status']); ?>">
                            <td><?= h($row['lot_no']); ?></td>
                            <td><?= h($row['survey_no']); ?></td>
                            <td><?= h($row['barangay_name']); ?></td>
                            <td><?= h($row['current_claimant'] ?: $row['survey_claimant'] ?: '—'); ?></td>
                            <td><?= format_number((float) $row['area_sqm']); ?></td>
                            <td>
                                <span class="<?= h(get_status_badge_class($row['status'])); ?>">
                                    <?= get_status_icon($row['status']); ?> <?= h(get_status_label($row['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No lot records available.</td></tr>
                <?php endif; ?>
                <tr id="lotsReportNoResults" style="display:none;">
                    <td colspan="6"><div class="empty-state-fancy" style="padding:25px;"><h3>No Matching Lots</h3><p>Try different keywords or status.</p></div></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<script>
function filterLotsReport() {
    const text = document.getElementById('lotsReportFilter').value.toLowerCase();
    const status = document.getElementById('lotsReportStatusFilter').value;
    const rows = document.querySelectorAll('#lotsReportBody .lots-report-row');
    const noResults = document.getElementById('lotsReportNoResults');
    let visible = 0;
    rows.forEach(row => {
        const textMatch = row.dataset.text.includes(text);
        const statusMatch = !status || row.dataset.status === status;
        const show = textMatch && statusMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    noResults.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
    document.getElementById('lotsReportCounter').textContent = visible + ' lots';
}

function exportBarangaySummaryCSV() {
    const table = document.querySelector('.panel table.table-compact');
    let csv = 'Barangay,Whole Area (sqm),Lots,Lot Area (sqm),Infra. Deductions,Community Deductions,Total Deductions,After Deductions,Remaining Balance\n';
    table.querySelectorAll('tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 2) return;
        const vals = Array.from(cells).slice(0, 9).map(c => '"' + c.textContent.trim().replace(/\n.*/s, '') + '"');
        csv += vals.join(',') + '\n';
    });
    downloadCSVReport(csv, 'barangay_summary.csv');
}

function exportLotsCSV() {
    const rows = document.querySelectorAll('#lotsReportBody .lots-report-row');
    let csv = 'Lot No,Survey No,Barangay,Claimant,Area (sqm),Status\n';
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const c = row.querySelectorAll('td');
        csv += `"${c[0].textContent.trim()}","${c[1].textContent.trim()}","${c[2].textContent.trim()}","${c[3].textContent.trim()}","${c[4].textContent.trim()}","${c[5].textContent.trim()}"\n`;
    });
    downloadCSVReport(csv, 'identified_lots.csv');
}

function downloadCSVReport(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = filename; a.click();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
