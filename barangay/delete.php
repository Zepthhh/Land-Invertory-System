<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(app_url('/barangay/index.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid barangay selected.');
    redirect(app_url('/barangay/index.php'));
}

// Fetch name for audit logging
$stmtName = $mysqli->prepare('SELECT name FROM barangay WHERE id = ?');
$stmtName->bind_param('i', $id);
$stmtName->execute();
$brgy = $stmtName->get_result()->fetch_assoc();
$stmtName->close();

if ($brgy) {
    $stmt = $mysqli->prepare('DELETE FROM barangay WHERE id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        log_action($mysqli, 'Delete Barangay', "Deleted Barangay: {$brgy['name']} (ID: $id) and all associated records.");
        set_flash('success', 'Barangay deleted successfully.');
    } else {
        set_flash('error', 'Failed to delete barangay: ' . $mysqli->error);
    }
    $stmt->close();
} else {
    set_flash('error', 'Barangay record not found.');
}

redirect(app_url('/barangay/index.php'));

