<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $allowedTypes = land_use_types();

    if ($barangayId <= 0 || $area <= 0 || !in_array($type, $allowedTypes, true)) {
        set_flash('error', 'Please complete all land use fields with valid values.');
        redirect(app_url('/land_use/create.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO land_use (barangay_id, type, area_sqm) VALUES (?, ?, ?)');
    $stmt->bind_param('isd', $barangayId, $type, $area);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Create Land Use', "Created Land Use: $type ($area sqm) in Barangay ID $barangayId.");
    set_flash('success', 'Land use entry added successfully.');
    redirect(app_url('/land_use/index.php'));
}

$pageTitle = 'Add Land Use';
$pageDescription = 'Create a land use deduction entry for an existing barangay.';
$barangays = fetch_barangays($mysqli);
$landUseTypes = land_use_types();
$landUseFormAction = app_url('/land_use/create.php');
$landUseFormValues = [
    'id' => '',
    'barangay_id' => '',
    'type' => 'Road',
    'area_sqm' => '',
];
$landUseSubmitLabel = 'Save Land Use';
$landUseShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">New Land Use Entry</h2>
    <?php if (!$barangays): ?>
        <div class="empty-state">Add a barangay first before recording land use deductions.</div>
    <?php else: ?>
        <?php require __DIR__ . '/form.php'; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
