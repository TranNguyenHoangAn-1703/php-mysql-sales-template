<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$result = $conn->query("
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
");

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'CategoryID'   => (int) $row['CategoryID'],
        'CategoryName' => $row['CategoryName'],
    ];
}

echo json_encode(
    ['success' => true, 'data' => $data],
    JSON_UNESCAPED_UNICODE
);

$conn->close();
