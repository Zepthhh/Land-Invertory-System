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

require_once __DIR__ . '/includes/header.php';
?>
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
        <table>
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total Area (sqm)</th>
                    <th>Total Lots</th>
                    <th>Total Lot Area (sqm)</th>
                    <th>Alley/Road/Irrigation/Canal (sqm)</th>
                    <th>Church/School Site/Plaza (sqm)</th>
                    <th>Total Deductions (sqm)</th>
                    <th>Remaining Balance (sqm)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($barangaySummaries): ?>
                    <?php foreach ($barangaySummaries as $row): ?>
                        <tr>
                            <td><?= h($row['name']); ?></td>
                            <td><?= format_number((float) $row['total_area_sqm']); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['total_lot_area']); ?></td>
                            <td><?= format_number((float) $row['infrastructure_area']); ?></td>
                            <td><?= format_number((float) $row['community_area']); ?></td>
                            <td><?= format_number((float) $row['total_land_use']); ?></td>
                            <td><?= format_number((float) $row['remaining_balance']); ?></td>
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

<section class="grid-2">
    <div class="panel">
        <h2 class="panel-title">Quick Search</h2>
        <form method="get" action="<?= h(app_url('/reports/search.php')); ?>">
            <div class="search-box">
                <input type="text" name="q" placeholder="Search by lot number or survey number" required>
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2 class="panel-title">Status Overview</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Total Lots</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $statusResult = $mysqli->query("
                        SELECT status, COUNT(*) AS total
                        FROM lots
                        GROUP BY status
                        ORDER BY FIELD(status, 'Unapplied', 'Applied', 'Titled', 'Conflict')
                    ");
                    $statuses = $statusResult ? $statusResult->fetch_all(MYSQLI_ASSOC) : [];
                    ?>
                    <?php if ($statuses): ?>
                        <?php foreach ($statuses as $status): ?>
                            <tr>
                                <td><span class="<?= h(get_status_badge_class($status['status'])); ?>"><?= h(get_status_label($status['status'])); ?></span></td>
                                <td><?= h((string) $status['total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">No lot statuses recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
