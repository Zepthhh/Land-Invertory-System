<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/land_use/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid land use entry selected.');
    redirect(app_url('/land_use/index.php'));
}

// Fetch details for logging
$stmtDetails = $mysqli->prepare('SELECT type, area_sqm, barangay_id FROM land_use WHERE id = ?');
$stmtDetails->bind_param('i', $id);
$stmtDetails->execute();
$lu = $stmtDetails->get_result()->fetch_assoc();
$stmtDetails->close();

if ($lu) {
    $stmt = $mysqli->prepare('DELETE FROM land_use WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        log_action($mysqli, 'Delete Land Use', "Deleted Land Use: Type: {$lu['type']}, Area: {$lu['area_sqm']} sqm, Barangay ID: {$lu['barangay_id']}.");
        set_flash('success', 'Land use entry deleted successfully.');
    } else {
        set_flash('error', 'Failed to delete land use entry: ' . $mysqli->error);
    }
    $stmt->close();
} else {
    set_flash('error', 'Land use entry not found.');
}

redirect(app_url('/land_use/index.php'));

