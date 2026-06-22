<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid barangay selected.');
    redirect(app_url('/barangay/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $municipalityId = (int)($_POST['municipality_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $totalArea = (float) ($_POST['total_area_sqm'] ?? 0);

    if ($municipalityId <= 0 || $name === '' || $totalArea <= 0) {
        set_flash('error', 'Please provide a valid municipality, barangay name, and total area.');
        redirect(app_url('/barangay/edit.php?id=' . $id));
    }

    $stmt = $mysqli->prepare('UPDATE barangay SET municipality_id = ?, name = ?, total_area_sqm = ? WHERE id = ?');
    $stmt->bind_param('isdi', $municipalityId, $name, $totalArea, $id);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Edit Barangay', "Updated Barangay details: ID: $id, Name: $name, Area: $totalArea sqm.");
    set_flash('success', 'Barangay updated successfully.');
    redirect(app_url('/barangay/index.php'));
}

$stmt = $mysqli->prepare('SELECT id, municipality_id, name, total_area_sqm FROM barangay WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$barangay = $result->fetch_assoc();
$stmt->close();

if (!$barangay) {
    set_flash('error', 'Barangay record not found.');
    redirect(app_url('/barangay/index.php'));
}

$pageTitle = 'Edit Barangay';
$pageDescription = 'Update barangay information and total area.';
$barangayFormAction = app_url('/barangay/edit.php?id=' . $id);
$barangayFormValues = $barangay;
$barangaySubmitLabel = 'Update Barangay';
$barangayShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">Edit Barangay</h2>
    <?php require __DIR__ . '/form.php'; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
