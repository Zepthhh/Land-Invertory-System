<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid lot selected.');
    redirect(app_url('/lots/index.php'));
}

$barangays = fetch_barangays($mysqli);
$lotStatuses = lot_statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotNo = trim($_POST['lot_no'] ?? '');
    $surveyNo = trim($_POST['survey_no'] ?? '');
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $status = $_POST['status'] ?? '';
    $allowedStatuses = $lotStatuses;

    if ($lotNo === '' || $surveyNo === '' || $barangayId <= 0 || $area <= 0 || !in_array($status, $allowedStatuses, true)) {
        set_flash('error', 'Please provide valid lot information.');
        redirect(app_url('/lots/edit.php?id=' . $id));
    }

    $stmt = $mysqli->prepare('UPDATE lots SET lot_no = ?, survey_no = ?, barangay_id = ?, area_sqm = ?, status = ? WHERE id = ?');
    $stmt->bind_param('ssidsi', $lotNo, $surveyNo, $barangayId, $area, $status, $id);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Lot updated successfully.');
    redirect(app_url('/lots/index.php'));
}

$stmt = $mysqli->prepare('SELECT id, lot_no, survey_no, barangay_id, area_sqm, status FROM lots WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$lot = $result->fetch_assoc();
$stmt->close();

if (!$lot) {
    set_flash('error', 'Lot record not found.');
    redirect(app_url('/lots/index.php'));
}

$pageTitle = 'Edit Lot';
$pageDescription = 'Update lot number, survey number, area, barangay, and status.';
$lotFormAction = app_url('/lots/edit.php?id=' . $id);
$lotFormValues = $lot;
$lotSubmitLabel = 'Update Lot';
$lotShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">Edit Lot</h2>
    <?php require __DIR__ . '/form.php'; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
