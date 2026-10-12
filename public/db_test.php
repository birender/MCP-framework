<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getPdo();

    $stmt = $pdo->query('SELECT VERSION() AS mysql_version');

    echo json_encode([
        'status' => 'connected',
        'database' => 'MySQL',
        'mysql_version' => $stmt->fetch()['mysql_version'],
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);

    // Keep database details out of the browser response.
    error_log('ERP database connection failed: ' . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Check server logs.',
    ], JSON_PRETTY_PRINT);
}
