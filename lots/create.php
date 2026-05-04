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
        redirect(app_url('/lots/create.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO lots (lot_no, survey_no, barangay_id, area_sqm, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssids', $lotNo, $surveyNo, $barangayId, $area, $status);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Lot added successfully.');
    redirect(app_url('/lots/index.php'));
}

$pageTitle = 'Add Lot';
$pageDescription = 'Create a lot record tied to an existing barangay.';
$barangays = fetch_barangays($mysqli);
$lotStatuses = lot_statuses();
$lotFormAction = app_url('/lots/create.php');
$lotFormValues = [
    'id' => '',
    'lot_no' => '',
    'survey_no' => '',
    'barangay_id' => '',
    'area_sqm' => '',
    'status' => 'Unapplied',
];
$lotSubmitLabel = 'Save Lot';
$lotShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">New Lot</h2>
    <?php if (!$barangays): ?>
        <div class="empty-state">Add a barangay first before creating lot records.</div>
    <?php else: ?>
        <?php require __DIR__ . '/form.php'; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
