<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotNo = trim($_POST['lot_no'] ?? '');
    $surveyNo = trim($_POST['survey_no'] ?? '');
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowedStatuses = lot_statuses();

    if ($lotNo === '' || $surveyNo === '' || $barangayId <= 0 || $area <= 0 || !in_array($status, $allowedStatuses, true)) {
        set_flash('error', 'Please complete all lot fields with valid values.');
        redirect(app_url('/lots/index.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO lots (lot_no, survey_no, barangay_id, area_sqm, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssids', $lotNo, $surveyNo, $barangayId, $area, $status);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Lot added successfully.');
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
];
$lotSubmitLabel = 'Save Lot';
$lotShowBack = false;
$lotsResult = $mysqli->query("
    SELECT l.id, l.lot_no, l.survey_no, b.name AS barangay_name, l.area_sqm, l.status, l.current_claimant, l.dominant_use
    FROM lots l
    INNER JOIN barangay b ON b.id = l.barangay_id
    ORDER BY l.id DESC
");
$lots = $lotsResult ? $lotsResult->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . '/../includes/header.php';
?>
<section class="grid-2">
    <div class="panel">
        <h2 class="panel-title">Add Lot</h2>
        <?php if (!$barangays): ?>
            <div class="empty-state">Add a barangay first before creating lot records.</div>
        <?php else: ?>
            <div class="actions actions-spread">
                <p class="section-text">The list page still supports quick entry, and the dedicated create page is available when you want a separate screen.</p>
                <a class="btn btn-secondary" href="<?= h(app_url('/lots/create.php')); ?>">Open Create Page</a>
            </div>
            <?php require __DIR__ . '/form.php'; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2 class="panel-title">Lot List</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lot No</th>
                        <th>Survey No</th>
                        <th>Barangay</th>
                        <th>Current Claimant</th>
                        <th>Area (sqm)</th>
                        <th>Dominant Use</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lots): ?>
                        <?php foreach ($lots as $lot): ?>
                            <tr>
                                <td><?= h((string) $lot['id']); ?></td>
                                <td><?= h($lot['lot_no']); ?></td>
                                <td><?= h($lot['survey_no']); ?></td>
                                <td><?= h($lot['barangay_name']); ?></td>
                                <td><?= h($lot['current_claimant']); ?></td>
                                <td><?= format_number((float) $lot['area_sqm']); ?></td>
                                <td><?= h($lot['dominant_use']); ?></td>
                                <td><span class="<?= h(get_status_badge_class($lot['status'])); ?>"><?= h(get_status_label($lot['status'])); ?></span></td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="btn btn-secondary" href="<?= h(app_url('/lots/edit.php?id=' . $lot['id'])); ?>">Edit</a>
                                        <form method="post" action="<?= h(app_url('/lots/delete.php')); ?>" onsubmit="return confirm('Delete this lot record?');">
                                            <input type="hidden" name="id" value="<?= h((string) $lot['id']); ?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No lot entries found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
