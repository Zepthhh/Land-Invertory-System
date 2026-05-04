<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid barangay selected.');
    redirect(app_url('/barangay/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $totalArea = (float) ($_POST['total_area_sqm'] ?? 0);

    if ($name === '' || $totalArea <= 0) {
        set_flash('error', 'Please provide a valid barangay name and total area.');
        redirect(app_url('/barangay/edit.php?id=' . $id));
    }

    $stmt = $mysqli->prepare('UPDATE barangay SET name = ?, total_area_sqm = ? WHERE id = ?');
    $stmt->bind_param('sdi', $name, $totalArea, $id);
    $stmt->execute();
    $stmt->close();

    set_flash('success', 'Barangay updated successfully.');
    redirect(app_url('/barangay/index.php'));
}

$stmt = $mysqli->prepare('SELECT id, name, total_area_sqm FROM barangay WHERE id = ?');
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
