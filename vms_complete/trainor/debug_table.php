<?php
// debug_table.php - Check tbl_train_sked structure
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header("Content-Type: application/json; charset=utf-8");

try {
    // Get table structure
    $stmt = $pdo->query("DESCRIBE tbl_train_sked");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'columns' => $columns
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}