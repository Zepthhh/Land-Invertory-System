<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Reports';
$pageDescription = 'Barangay totals, grouped land-use deductions, survey totals, and identified lot areas.';

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

$surveySummaryResult = $mysqli->query("
    SELECT survey_no, COUNT(*) AS total_lots, SUM(area_sqm) AS total_area_sqm
    FROM lots
    GROUP BY survey_no
    ORDER BY total_area_sqm DESC, survey_no ASC
");
$surveySummary = $surveySummaryResult ? $surveySummaryResult->fetch_all(MYSQLI_ASSOC) : [];

$statusCountResult = $mysqli->query("
    SELECT status, COUNT(*) AS total
    FROM lots
    GROUP BY status
    ORDER BY FIELD(status, 'Unapplied', 'Applied', 'Titled', 'Conflict')
");
$statusCounts = $statusCountResult ? $statusCountResult->fetch_all(MYSQLI_ASSOC) : [];

$identifiedLotsResult = $mysqli->query("
    SELECT l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.current_claimant, l.tax_declarant, l.status
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    ORDER BY b.name ASC, l.survey_no ASC, CAST(l.lot_no AS UNSIGNED), l.lot_no ASC
    LIMIT 300
");
$identifiedLots = $identifiedLotsResult ? $identifiedLotsResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/../includes/header.php';
?>
<section class="cards">
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

<section class="panel">
    <div class="inline-actions">
        <a class="btn btn-secondary" href="<?= h(app_url('/reports/search.php')); ?>">Open Search</a>
    </div>
</section>

<section class="panel">
    <h2 class="panel-title">Barangay Summary</h2>
    <p class="section-text">Each barangay shows total square meters, total lots, grouped deductions for `Alley/Road/Irrigation/Canal` and `Church/School Site/Plaza`, then the remaining area after deductions and identified lots.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Whole Area (sqm)</th>
                    <th>Total Lots</th>
                    <th>Identified Lot Area (sqm)</th>
                    <th>Alley/Road/Irrigation/Canal (sqm)</th>
                    <th>Church/School Site/Plaza (sqm)</th>
                    <th>Total Deductions (sqm)</th>
                    <th>Area After Deductions (sqm)</th>
                    <th>Remaining Balance (sqm)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($barangaySummary): ?>
                    <?php foreach ($barangaySummary as $row): ?>
                        <?php
                        $wholeBarangayArea = (float) $row['total_area_sqm'];
                        $areaAfterDeductions = (float) $row['area_after_land_use'];
                        $remainingBalance = (float) $row['remaining_balance'];
                        ?>
                        <tr>
                            <td><?= h($row['name']); ?></td>
                            <td><?= format_number($wholeBarangayArea); ?></td>
                            <td><?= h((string) $row['total_lots']); ?></td>
                            <td><?= format_number((float) $row['total_lot_area']); ?></td>
                            <td><?= format_number((float) $row['infrastructure_area']); ?></td>
                            <td><?= format_number((float) $row['community_area']); ?></td>
                            <td><?= format_number((float) $row['total_land_use']); ?></td>
                            <td><?= format_number($areaAfterDeductions); ?></td>
                            <td><?= format_number($remainingBalance); ?></td>
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

<section class="grid-2">
    <div class="panel">
        <h2 class="panel-title">Survey Total Per Survey Number</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Survey No</th>
                        <th>Total Lots</th>
                        <th>Total Area (sqm)</th>
                        <th>Whole Area Remaining (sqm)</th>
                        <th>Share of Whole Area</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($surveySummary): ?>
                        <?php foreach ($surveySummary as $row): ?>
                            <?php
                            $surveyArea = (float) $row['total_area_sqm'];
                            $wholeRemaining = $wholeArea - $surveyArea;
                            $wholeShare = $wholeArea > 0 ? ($surveyArea / $wholeArea) * 100 : 0.0;
                            ?>
                            <tr>
                                <td><?= h($row['survey_no']); ?></td>
                                <td><?= h((string) $row['total_lots']); ?></td>
                                <td><?= format_number($surveyArea); ?></td>
                                <td><?= format_number($wholeRemaining); ?></td>
                                <td><?= format_percent($wholeShare); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No survey records available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2 class="panel-title">Status Count</h2>
        <div class="table-wrap">
            <table>
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
                                <td><span class="<?= h(get_status_badge_class($row['status'])); ?>"><?= h(get_status_label($row['status'])); ?></span></td>
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
    <h2 class="panel-title">Identify Lot and Area (sqm)</h2>
    <p class="section-text">This report lists the identified lot number, its survey number, claimant, exact area in square meters, and current status.</p>
    <div class="table-wrap">
        <table>
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
            <tbody>
                <?php if ($identifiedLots): ?>
                    <?php foreach ($identifiedLots as $row): ?>
                        <tr>
                            <td><?= h($row['lot_no']); ?></td>
                            <td><?= h($row['survey_no']); ?></td>
                            <td><?= h($row['barangay_name']); ?></td>
                            <td><?= h($row['current_claimant'] ?: $row['tax_declarant']); ?></td>
                            <td><?= format_number((float) $row['area_sqm']); ?></td>
                            <td><span class="<?= h(get_status_badge_class($row['status'])); ?>"><?= h(get_status_label($row['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No lot records available.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
