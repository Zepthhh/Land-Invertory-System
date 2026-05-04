<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid land use entry selected.');
    redirect(app_url('/land_use/index.php'));
}

$barangays = fetch_barangays($mysqli);
$landUseTypes = land_use_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $area = (float) ($_POST['area_sqm'] ?? 0);

    if ($barangayId <= 0 || $area <= 0 || !in_array($type, $landUseTypes, true)) {
        set_flash('error', 'Please provide valid land use details.');
        redirect(app_url('/land_use/edit.php?id=' . $id));
    }

    $stmt = $mysqli->prepare('UPDATE land_use SET barangay_id = ?, type = ?, area_sqm = ? WHERE id = ?');
    $stmt->bind_param('isdi', $barangayId, $type, $area, $id);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Land use entry updated successfully.');
    redirect(app_url('/land_use/index.php'));
}

$stmt = $mysqli->prepare('SELECT id, barangay_id, type, area_sqm FROM land_use WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$entry = $result->fetch_assoc();
$stmt->close();

if (!$entry) {
    set_flash('error', 'Land use record not found.');
    redirect(app_url('/land_use/index.php'));
}

$pageTitle = 'Edit Land Use';
$pageDescription = 'Update the land use type, barangay, and deducted area.';
$landUseFormAction = app_url('/land_use/edit.php?id=' . $id);
$landUseFormValues = $entry;
$landUseSubmitLabel = 'Update Land Use';
$landUseShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">Edit Land Use</h2>
    <?php require __DIR__ . '/form.php'; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
