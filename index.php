<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Enforce login
require_login();

$pageTitle = 'Dashboard';
$pageDescription = 'Barangay land overview with exact remaining and balance computations.';

// Fetch summary rows
$summaryResult = $mysqli->query("
    SELECT
        b.id,
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
$barangaySummaries = $summaryResult ? $summaryResult->fetch_all(MYSQLI_ASSOC) : [];

$dashboardCards = [
    'Barangays' => (string) count_table_rows($mysqli, 'barangay'),
    'Lots' => (string) count_table_rows($mysqli, 'lots'),
    'Land Use Entries' => (string) count_table_rows($mysqli, 'land_use'),
    'Total Barangay Area' => format_number(sum_table_area($mysqli, 'barangay', 'total_area_sqm')) . ' sqm',
];

// Fetch Recent Lots
$recentLotsResult = $mysqli->query("
    SELECT l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    ORDER BY l.id DESC
    LIMIT 5
");
$recentLots = $recentLotsResult ? $recentLotsResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Status Counts for Chart
$statusCountsResult = $mysqli->query("
    SELECT status, COUNT(*) AS count, COALESCE(SUM(area_sqm), 0) AS total_area
    FROM lots
    GROUP BY status
");
$statusData = [];
$totalLotsCount = 0;
// Prefill empty arrays
foreach (lot_statuses() as $st) {
    $statusData[$st] = ['count' => 0, 'area' => 0.0];
}
if ($statusCountsResult) {
    while ($row = $statusCountsResult->fetch_assoc()) {
        $statusData[$row['status']] = [
            'count' => (int) $row['count'],
            'area' => (float) $row['total_area']
        ];
        $totalLotsCount += (int) $row['count'];
    }
}

// Fetch Land Use Category Breakdown for Chart
$landUseBreakdownResult = $mysqli->query("
    SELECT type, SUM(area_sqm) AS total_area
    FROM land_use
    GROUP BY type
    ORDER BY total_area DESC
");
$landUseBreakdown = $landUseBreakdownResult ? $landUseBreakdownResult->fetch_all(MYSQLI_ASSOC) : [];
$totalLandUseArea = 0.0;
foreach ($landUseBreakdown as $lu) {
    $totalLandUseArea += (float)$lu['total_area'];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Quick Search Bar -->
<div class="search-bar-wrapper" style="margin-bottom: 25px;">
    <form method="get" action="<?= h(app_url('/reports/search.php')); ?>" style="margin: 0;">
        <div class="search-box" style="display: flex; gap: 10px; max-width: 700px;">
            <input type="text" name="q"
                placeholder="🔍  Search lot no, survey no, claimant, barangay..."
                style="flex: 1; padding: 12px 20px; font-size: 1rem; border-radius: 12px; background: var(--panel-bg); border: 1px solid var(--panel-border); backdrop-filter: var(--glass-blur); box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: #fff; font-family: inherit;">
            <button class="btn btn-primary" type="submit" style="border-radius: 12px; padding: 12px 25px;">Search</button>
            <a class="btn btn-secondary" href="<?= h(app_url('/reports/search.php')); ?>" style="border-radius: 12px; padding: 12px 16px; white-space: nowrap;">Advanced Search</a>
        </div>
    </form>
</div>

<section class="cards">
    <?php foreach ($dashboardCards as $label => $value): ?>
        <div class="card">
            <div class="card-label"><?= h($label); ?></div>
            <div class="card-value count-up" data-target="<?= h($value); ?>"><?= h($value); ?></div>
        </div>
    <?php endforeach; ?>
</section>

<!-- OFFLINE SVG CHARTS PANEL GRID -->
<section class="grid-2" style="margin-bottom: 24px;">
    <!-- Chart Panel: Lot Status (SVG Donut) -->
    <div class="panel" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <h2 class="panel-title">Lot Status Distribution</h2>
        <div style="display: flex; flex: 1; align-items: center; justify-content: center; gap: 30px; flex-wrap: wrap; padding: 10px 0;">
            <!-- SVG Donut Chart -->
            <div style="position: relative; width: 150px; height: 150px;">
                <svg viewBox="0 0 100 100" width="100%" height="100%">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="rgba(255,255,255,0.03)" stroke-width="12"></circle>
                    <?php
                    $circumference = 2 * pi() * 40; // ~251.327
                    $currentOffset = 0.0;
                    
                    // Colors
                    $colors = [
                        'Titled' => 'var(--success)', // Green
                        'Applied' => '#3b82f6',       // Blue
                        'Conflict' => 'var(--danger)', // Red
                        'Unapplied' => 'var(--warning)', // Amber
                    ];

                    if ($totalLotsCount > 0) {
                        foreach ($statusData as $status => $data) {
                            $percent = $data['count'] / $totalLotsCount;
                            $strokeLength = $percent * $circumference;
                            $strokeOffset = $circumference - $strokeLength + $currentOffset;
                            
                            if ($data['count'] > 0) {
                                ?>
                                <circle cx="50" cy="50" r="40" 
                                        fill="transparent" 
                                        stroke="<?= $colors[$status]; ?>" 
                                        stroke-width="12" 
                                        stroke-dasharray="<?= $circumference; ?>" 
                                        stroke-dashoffset="<?= $strokeOffset; ?>"
                                        transform="rotate(-90 50 50)"
                                        style="transition: stroke-dashoffset 0.8s ease;">
                                </circle>
                                <?php
                            }
                            $currentOffset -= $strokeLength;
                        }
                    }
                    ?>
                </svg>
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <span style="font-size: 1.6rem; font-weight: 800; color: #fff;"><?= $totalLotsCount; ?></span>
                    <span style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">Total Lots</span>
                </div>
            </div>

            <!-- Legend with detailed counts -->
            <div style="display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 180px;">
                <?php foreach ($statusData as $status => $data):
                    $pct = $totalLotsCount > 0 ? ($data['count'] / $totalLotsCount) * 100 : 0.0;
                ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.88rem;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?= $colors[$status] ?>; box-shadow: 0 0 6px <?= $colors[$status] ?>;"></span>
                            <span style="color: #e2e8f0; font-weight: 500;"><?= h($status); ?></span>
                        </div>
                        <span style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">
                            <?= $data['count'] ?> (<?= format_percent($pct) ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Chart Panel: Land Use (SVG Horizontal Breakdown) -->
    <div class="panel" style="margin-bottom: 0;">
        <h2 class="panel-title">Land Use Category Breakdown</h2>
        <div style="display: flex; flex-direction: column; gap: 12px; padding: 10px 0;">
            <?php if ($landUseBreakdown): ?>
                <?php foreach (array_slice($landUseBreakdown, 0, 4) as $lu): 
                    $pct = $totalLandUseArea > 0 ? ($lu['total_area'] / $totalLandUseArea) * 100 : 0.0;
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px;">
                            <span style="font-weight: 600; color: #f1f5f9;"><?= h($lu['type']); ?></span>
                            <span style="color: var(--text-muted);"><?= format_number((float)$lu['total_area']) ?> sqm (<?= format_percent($pct) ?>)</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.03); border-radius: 4px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="width: <?= min(100, max(0, $pct)) ?>%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); border-radius: 4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($landUseBreakdown) > 4): 
                    $otherArea = 0.0;
                    foreach (array_slice($landUseBreakdown, 4) as $o) { $otherArea += (float)$o['total_area']; }
                    $pct = $totalLandUseArea > 0 ? ($otherArea / $totalLandUseArea) * 100 : 0.0;
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px;">
                            <span style="font-weight: 600; color: #f1f5f9;">Others</span>
                            <span style="color: var(--text-muted);"><?= format_number($otherArea) ?> sqm (<?= format_percent($pct) ?>)</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.03); border-radius: 4px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                            <div style="width: <?= min(100, max(0, $pct)) ?>%; height: 100%; background: linear-gradient(90deg, #9ca3af, #6b7280); border-radius: 4px;"></div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state-fancy" style="padding: 20px 0;">
                    <p style="font-size:0.9rem;">No land use category entries found yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Barangay Land Summary</h2>
    <div class="table-wrap">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total Area<br><small>(sqm)</small></th>
                    <th>Lots</th>
                    <th>Lot Area<br><small>(sqm)</small></th>
                    <th>Infra. Deductions<br><small>Alley/Road/Canal</small></th>
                    <th>Community Deductions<br><small>Church/School/Plaza</small></th>
                    <th>Total Deductions<br><small>(sqm)</small></th>
                    <th>Remaining Balance<br><small>(sqm)</small></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($barangaySummaries): ?>
                    <?php foreach ($barangaySummaries as $row): 
                        $total_area = (float) $row['total_area_sqm'];
                        $remaining = (float) $row['remaining_balance'];
                        $utilized = $total_area - $remaining;
                        $percent_utilized = ($total_area > 0) ? ($utilized / $total_area) * 100 : 0;
                        
                        $progress_class = '';
                        if ($percent_utilized > 90) $progress_class = 'danger';
                        elseif ($percent_utilized > 75) $progress_class = 'warning';
                    ?>
                        <tr>
                            <td><strong><?= h($row['name']); ?></strong></td>
                            <td><?= format_number($total_area); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['total_lot_area']); ?></td>
                            <td><?= format_number((float) $row['infrastructure_area']); ?></td>
                            <td><?= format_number((float) $row['community_area']); ?></td>
                            <td><?= format_number((float) $row['total_land_use']); ?></td>
                            <td style="min-width: 200px;">
                                <div style="font-weight: 600; color: <?= $remaining < 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                                    <?= format_number($remaining); ?> sqm
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar <?= $progress_class ?>" style="width: <?= min(100, max(0, $percent_utilized)) ?>%;"></div>
                                </div>
                                <div class="progress-text">
                                    <span>Used: <?= format_percent($percent_utilized) ?></span>
                                    <span>Free: <?= format_percent(100 - $percent_utilized) ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No barangay records found yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Recent Lot Registrations</h2>
    <div class="table-wrap">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Lot No</th>
                    <th>Survey No</th>
                    <th>Barangay</th>
                    <th>Area (sqm)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentLots): ?>
                    <?php foreach ($recentLots as $lot): ?>
                        <tr>
                            <td><?= h($lot['lot_no']); ?></td>
                            <td><?= h($lot['survey_no']); ?></td>
                            <td><?= h($lot['barangay_name']); ?></td>
                            <td><?= format_number((float) $lot['area_sqm']); ?></td>
                            <td>
                                <span class="<?= h(get_status_badge_class($lot['status'])); ?>">
                                    <?= get_status_icon($lot['status']); ?> <?= h(get_status_label($lot['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No lots registered yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
// Count-up animation for stat cards
function animateCountUp(el) {
    const text = el.dataset.target || el.textContent;
    const numMatch = text.match(/[\d,]+(?:\.\d+)?/);
    if (!numMatch) return;
    const numStr = numMatch[0].replace(/,/g, '');
    const target = parseFloat(numStr);
    if (isNaN(target) || target < 10) return;
    const suffix = text.replace(numMatch[0], '');
    const duration = 900;
    const startTime = performance.now();
    const startVal = 0;
    function step(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = startVal + (target - startVal) * eased;
        const formatted = current >= 1000
            ? current.toFixed(target % 1 ? 2 : 0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
            : current.toFixed(target % 1 ? 2 : 0);
        el.textContent = formatted + suffix;
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCountUp(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.count-up').forEach(el => observer.observe(el));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
