<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $search = trim($_GET['search'] ?? '');
    $filterStatus = $_GET['status'] ?? '';
    $filterBarangay = (int)($_GET['barangay_id'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 100));
    $offset = ($page - 1) * $limit;

    // Build WHERE clauses
    $whereClauses = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $whereClauses[] = "(l.lot_no LIKE ? OR l.survey_no LIKE ? OR l.claimant LIKE ? OR l.tenant LIKE ?)";
        $searchParam = "%{$search}%";
        array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
        $types .= 'ssss';
    }

    if ($filterStatus !== '') {
        $whereClauses[] = "l.status = ?";
        $params[] = $filterStatus;
        $types .= 's';
    }

    if ($filterBarangay > 0) {
        $whereClauses[] = "l.barangay_id = ?";
        $params[] = $filterBarangay;
        $types .= 'i';
    }

    $whereSql = '';
    if (count($whereClauses) > 0) {
        $whereSql = "WHERE " . implode(" AND ", $whereClauses);
    }

    // Count total rows
    $countSql = "SELECT COUNT(*) as total FROM lots l $whereSql";
    $stmtCount = $mysqli->prepare($countSql);
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $totalRows = (int)$stmtCount->get_result()->fetch_assoc()['total'];
    $stmtCount->close();

    $totalPages = ceil($totalRows / $limit);

    // Fetch data
    $sql = "
        SELECT l.*, b.name AS barangay_name, m.name AS municipality_name 
        FROM lots l 
        LEFT JOIN barangay b ON l.barangay_id = b.id
        LEFT JOIN municipality m ON b.municipality_id = m.id
        $whereSql
        ORDER BY l.id DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $lots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'data' => $lots,
        'pagination' => [
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
