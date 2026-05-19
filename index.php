<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Dashboard';
$pageDescription = 'Barangay land overview with exact remaining and balance computations.';

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



$recentLotsResult = $mysqli->query("
    SELECT l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    ORDER BY l.id DESC
    LIMIT 5
");
$recentLots = $recentLotsResult ? $recentLotsResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/includes/header.php';
?>

<div class="search-bar-wrapper" style="margin-bottom: 25px;">
    <form method="get" action="<?= h(app_url('/reports/search.php')); ?>" style="margin: 0;">
        <div class="search-box" style="display: flex; gap: 10px; max-width: 600px;">
            <select name="q" required style="flex: 1; padding: 12px 20px; font-size: 1rem; border-radius: 12px; background: var(--panel-bg); border: 1px solid var(--panel-border); backdrop-filter: var(--glass-blur); box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: #fff; appearance: auto; cursor: pointer;">
                <option value="" disabled selected>Select a status to search...</option>
                <?php foreach (lot_statuses() as $status): ?>
                    <option value="<?= h($status); ?>"><?= h(get_status_label($status)); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit" style="border-radius: 12px; padding: 12px 25px;">Search</button>
        </div>
    </form>
</div>

<section class="cards">
    <?php foreach ($dashboardCards as $label => $value): ?>
        <div class="card">
            <div class="card-label"><?= h($label); ?></div>
            <div class="card-value"><?= h($value); ?></div>
        </div>
    <?php endforeach; ?>
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
                            <td><span class="<?= h(get_status_badge_class($lot['status'])); ?>"><?= h(get_status_label($lot['status'])); ?></span></td>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
