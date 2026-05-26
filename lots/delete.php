<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/lots/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid lot selected.');
    redirect(app_url('/lots/index.php'));
}

// Fetch details for logging
$stmtLot = $mysqli->prepare('SELECT lot_no, survey_no, area_sqm FROM lots WHERE id = ?');
$stmtLot->bind_param('i', $id);
$stmtLot->execute();
$lot = $stmtLot->get_result()->fetch_assoc();
$stmtLot->close();

if ($lot) {
    $stmt = $mysqli->prepare('DELETE FROM lots WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        log_action($mysqli, 'Delete Lot', "Deleted lot: Lot No: {$lot['lot_no']}, Survey No: {$lot['survey_no']}, Area: {$lot['area_sqm']} sqm.");
        set_flash('success', 'Lot deleted successfully.');
    } else {
        set_flash('error', 'Failed to delete lot: ' . $mysqli->error);
    }
    $stmt->close();
} else {
    set_flash('error', 'Lot record not found.');
}

redirect(app_url('/lots/index.php'));

