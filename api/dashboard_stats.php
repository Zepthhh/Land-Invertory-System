<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    // 1. Fetch Barangay Summaries
    $summaryResult = $mysqli->query("
        SELECT
            b.id,
            b.name,
            b.total_area_sqm,
            COALESCE(l.total_lots, 0) AS total_lots,
            COALESCE(l.total_lot_area, 0) AS total_lot_area,
            COALESCE(u.infrastructure_area, 0) AS infrastructure_area,
            COALESCE(u.community_area, 0) AS community_area,
            COALESCE(u.total_land_use, 0) AS total_land_use,
            (b.total_area_sqm - COALESCE(u.total_land_use, 0)) AS area_after_land_use,
            ((b.total_area_sqm - COALESCE(u.total_land_use, 0)) - COALESCE(l.total_lot_area, 0)) AS remaining_balance,
            m.name AS municipality_name
        FROM barangay b
        JOIN municipality m ON b.municipality_id = m.id
        LEFT JOIN (
            SELECT barangay_id, COUNT(*) AS total_lots, SUM(area_sqm) AS total_lot_area
            FROM lots
            GROUP BY barangay_id
        ) l ON l.barangay_id = b.id
        LEFT JOIN (
            SELECT
                barangay_id,
                SUM(CASE WHEN type IN ('Alley', 'Road', 'Irrigation', 'Canal') THEN area_sqm ELSE 0 END) AS infrastructure_area,
                SUM(CASE WHEN type IN ('Church', 'School', 'School Site', 'Plaza') THEN area_sqm ELSE 0 END) AS community_area,
                SUM(area_sqm) AS total_land_use
            FROM land_use
            GROUP BY barangay_id
        ) u ON u.barangay_id = b.id
        ORDER BY m.name ASC, b.name ASC
    ");
    $barangaySummaries = $summaryResult ? $summaryResult->fetch_all(MYSQLI_ASSOC) : [];

    // 2. Calculate Municipality Summaries
    $municipalitySummaries = [];
    foreach ($barangaySummaries as $row) {
        $mName = $row['municipality_name'];
        if (!isset($municipalitySummaries[$mName])) {
            $municipalitySummaries[$mName] = [
                'name' => $mName,
                'total_area' => 0.0,
                'total_lots' => 0,
                'remaining_balance' => 0.0,
                'barangay_count' => 0
            ];
        }
        $municipalitySummaries[$mName]['total_area'] += (float)$row['total_area_sqm'];
        $municipalitySummaries[$mName]['total_lots'] += (int)$row['total_lots'];
        $municipalitySummaries[$mName]['remaining_balance'] += (float)$row['remaining_balance'];
        $municipalitySummaries[$mName]['barangay_count']++;
    }

    // 3. Fetch Municipality Cards
    $munCountResult = $mysqli->query("
        SELECT m.id, m.name, COUNT(b.id) AS barangay_count
        FROM municipality m
        LEFT JOIN barangay b ON m.id = b.municipality_id
        GROUP BY m.id
        ORDER BY m.name ASC
    ");
    $municipalityCards = $munCountResult ? $munCountResult->fetch_all(MYSQLI_ASSOC) : [];

    // 4. Fetch Recent Lots
    $recentLotsResult = $mysqli->query("
        SELECT l.id, l.lot_no, l.survey_no, b.name AS barangay_name, m.name AS municipality_name, l.area_sqm, l.status
        FROM lots l
        INNER JOIN barangay b ON b.id = l.barangay_id
        INNER JOIN municipality m ON b.municipality_id = m.id
        ORDER BY l.id DESC
        LIMIT 5
    ");
    $recentLots = $recentLotsResult ? $recentLotsResult->fetch_all(MYSQLI_ASSOC) : [];

    // 5. Fetch Status Counts
    $statusCountsResult = $mysqli->query("
        SELECT status, COUNT(*) AS count, COALESCE(SUM(area_sqm), 0) AS total_area
        FROM lots
        GROUP BY status
    ");
    $statusData = [];
    $totalLotsCount = 0;
    foreach (lot_statuses() as $st) {
        $statusData[$st] = ['count' => 0, 'area' => 0.0];
    }
    if ($statusCountsResult) {
        while ($row = $statusCountsResult->fetch_assoc()) {
            $statusData[$row['status']] = [
                'count' => (int) $row['count'],
                'area' => (float) $row['total_area']
            ];
            $totalLotsCount += (int) $row['count'];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'barangaySummaries' => $barangaySummaries,
            'municipalitySummaries' => array_values($municipalitySummaries),
            'municipalityCards' => $municipalityCards,
            'recentLots' => $recentLots,
            'statusData' => $statusData,
            'totalLotsCount' => $totalLotsCount
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
