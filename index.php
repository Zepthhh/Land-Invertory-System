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
        ((b.total_area_sqm - COALESCE(u.total_land_use, 0)) - COALESCE(l.total_lot_area, 0)) AS remaining_balance,
        m.name AS municipality_name
    FROM barangay b
    JOIN municipality m ON b.municipality_id = m.id
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
    ORDER BY m.name ASC, b.name ASC
");
$barangaySummaries = $summaryResult ? $summaryResult->fetch_all(MYSQLI_ASSOC) : [];

// Calculate Municipality Summaries
$municipalitySummaries = [];
foreach ($barangaySummaries as $row) {
    $mName = $row['municipality_name'];
    if (!isset($municipalitySummaries[$mName])) {
        $municipalitySummaries[$mName] = [
            'name' => $mName,
            'total_area' => 0.0,
            'total_lots' => 0,
            'remaining_balance' => 0.0,
            'barangay_count' => 0
        ];
    }
    $municipalitySummaries[$mName]['total_area'] += (float)$row['total_area_sqm'];
    $municipalitySummaries[$mName]['total_lots'] += (int)$row['total_lots'];
    $municipalitySummaries[$mName]['remaining_balance'] += (float)$row['remaining_balance'];
    $municipalitySummaries[$mName]['barangay_count']++;
}

// Fetch Municipality Barangay Counts for Cards
$munCountResult = $mysqli->query("
    SELECT m.id, m.name, COUNT(b.id) AS barangay_count
    FROM municipality m
    LEFT JOIN barangay b ON m.id = b.municipality_id
    GROUP BY m.id
    ORDER BY m.name ASC
");
$municipalityCards = $munCountResult ? $munCountResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Recent Lots
$recentLotsResult = $mysqli->query("
    SELECT l.lot_no, l.survey_no, b.name AS barangay_name, m.name AS municipality_name, l.area_sqm, l.status
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    INNER JOIN municipality m ON b.municipality_id = m.id
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

<h2 class="panel-title" style="margin-top: 0px;">Barangays per Municipality</h2>
<section class="cards" style="margin-bottom: 25px;">
    <?php foreach ($municipalityCards as $mun): ?>
        <div class="card" onclick="window.location='<?= h(app_url('/barangay/index.php?mun_id=' . $mun['id'])); ?>'" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)';this.style.boxShadow='0 10px 25px rgba(16,185,129,0.2)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)'">
            <div class="card-label" style="font-size: 0.9rem;"><?= h($mun['name']); ?></div>
            <div class="card-value count-up" data-target="<?= h((string)$mun['barangay_count']); ?>"><?= h((string)$mun['barangay_count']); ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">Barangays</div>
        </div>
    <?php endforeach; ?>
</section>

<!-- OFFLINE SVG CHARTS PANEL GRID -->
<section style="margin-bottom: 24px;">
    <!-- Chart Panel: Lot Status (SVG Donut) -->
    <div class="panel" style="margin-bottom: 0; display: flex; flex-direction: column;">
        <h2 class="panel-title">Lot Status Distribution</h2>
        <div style="display: flex; flex: 1; align-items: center; justify-content: flex-start; gap: 40px; flex-wrap: wrap; padding: 20px 10px;">
            <!-- SVG Donut Chart -->
            <div style="position: relative; width: 160px; height: 160px; flex-shrink: 0;">
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
                            
                            if ($data['count'] > 0) {
                                ?>
                                <circle cx="50" cy="50" r="40" 
                                        fill="transparent" 
                                        stroke="<?= $colors[$status]; ?>" 
                                        stroke-width="12" 
                                        stroke-dasharray="<?= $strokeLength; ?> <?= $circumference; ?>" 
                                        stroke-dashoffset="<?= -$currentOffset; ?>"
                                        transform="rotate(-90 50 50)"
                                        style="transition: stroke-dashoffset 0.8s ease;">
                                </circle>
                                <?php
                            }
                            $currentOffset += $strokeLength;
                        }
                    }
                    ?>
                </svg>
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <span style="font-size: 1.8rem; font-weight: 800; color: #fff;"><?= number_format($totalLotsCount); ?></span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Total Lots</span>
                </div>
            </div>

            <!-- Premium Data Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; flex: 1;">
                <?php foreach ($statusData as $status => $data): ?>
                    <div style="background: rgba(0,0,0,0.25); padding: 14px 18px; border-radius: 12px; border-left: 4px solid <?= $colors[$status] ?>; border-top: 1px solid rgba(255,255,255,0.03); border-right: 1px solid rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.03);">
                        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px;"><?= h($status); ?></div>
                        <div style="font-size: 1.4rem; font-weight: 700; color: #f1f5f9;"><?= number_format((float)$data['count']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Municipality Summary</h2>
    <div class="table-wrap">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Municipality</th>
                    <th>Barangays</th>
                    <th>Total Area<br><small>(sqm)</small></th>
                    <th>Total Lots</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($municipalitySummaries): ?>
                    <?php foreach ($municipalitySummaries as $row): 
                        $total_area = $row['total_area'];
                        $remaining = $row['remaining_balance'];
                        $utilized = $total_area - $remaining;
                        $percent_utilized = ($total_area > 0) ? ($utilized / $total_area) * 100 : 0;
                        $progress_class = '';
                        if ($percent_utilized > 90) $progress_class = 'danger';
                        elseif ($percent_utilized > 75) $progress_class = 'warning';
                    ?>
                        <tr class="clickable-row" onclick="openSummaryModal('municipality', null, '<?= h(addslashes($row['name'])); ?>')">
                            <td><strong><?= h($row['name']); ?></strong></td>
                            <td><?= h((string)$row['barangay_count']); ?></td>
                            <td><?= format_number($total_area); ?></td>
                            <td><?= h((string)$row['total_lots']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No municipality data available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Barangay Land Summary</h2>
    <div class="table-wrap">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Municipality</th>
                    <th>Barangay</th>
                    <th>Total Area<br><small>(sqm)</small></th>
                    <th>Lots</th>
                    <th>Lot Area<br><small>(sqm)</small></th>
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
                        <tr class="clickable-row" onclick="openSummaryModal('barangay', <?= (int)$row['id'] ?>)">
                            <td><?= h($row['municipality_name']); ?></td>
                            <td><strong><?= h($row['name']); ?></strong></td>
                            <td><?= format_number($total_area); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['total_lot_area']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No barangay records found yet.</td>
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
                    <th>Municipality</th>
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
                            <td><?= h($lot['municipality_name']); ?></td>
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
                        <td colspan="6">No lots registered yet.</td>
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

<?php require_once __DIR__ . '/includes/modal_summary.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
