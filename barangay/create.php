<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $totalArea = (float) ($_POST['total_area_sqm'] ?? 0);

    if ($name === '' || $totalArea <= 0) {
        set_flash('error', 'Please provide a barangay name and a valid total area.');
        redirect(app_url('/barangay/create.php'));
    }

    $stmt = $mysqli->prepare('INSERT INTO barangay (name, total_area_sqm) VALUES (?, ?)');
    $stmt->bind_param('sd', $name, $totalArea);
    $stmt->execute();
    $stmt->close();

    log_action($mysqli, 'Create Barangay', "Created Barangay: $name, Area: $totalArea sqm.");
    set_flash('success', 'Barangay added successfully.');
    redirect(app_url('/barangay/index.php'));
}

$pageTitle = 'Add Barangay';
$pageDescription = 'Create a barangay record using the database structure already provided.';
$barangayFormAction = app_url('/barangay/create.php');
$barangayFormValues = [
    'id' => '',
    'name' => '',
    'total_area_sqm' => '',
];
$barangaySubmitLabel = 'Save Barangay';
$barangayShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">New Barangay</h2>
    <?php require __DIR__ . '/form.php'; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
