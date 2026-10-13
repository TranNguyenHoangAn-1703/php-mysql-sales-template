<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$keyword = trim($_GET['keyword'] ?? '');
$categoryID = (int) ($_GET['category_id'] ?? 0);

$where = "p.IsActive = 1 AND p.CategoryID = c.CategoryID";
$types = '';
$params = [];

if ($keyword !== '') {
    $where .= " AND p.ProductName LIKE ?";
    $types .= 's';
    $params[] = '%' . $keyword . '%';
}

if ($categoryID > 0) {
    $where .= " AND p.CategoryID = ?";
    $types .= 'i';
    $params[] = $categoryID;
}

$sql = "
    SELECT
        p.ProductID, p.ProductName, p.Price, p.Unit, p.StockQuantity,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
            ORDER BY pi.IsPrimary DESC, pi.SortOrder
            LIMIT 1
        ) AS ImageFile
    FROM products p, categories c
    WHERE $where
    ORDER BY p.ProductID
";

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'ProductID'     => (int) $row['ProductID'],
        'ProductName'   => $row['ProductName'],
        'Price'         => (float) $row['Price'],
        'Unit'          => $row['Unit'],
        'StockQuantity' => (int) $row['StockQuantity'],
        'CategoryName'  => $row['CategoryName'],
        'ImageUrl'      => $row['ImageFile'] ? '/uploads/products/' . $row['ImageFile'] : null,
    ];
}

echo json_encode(
    ['success' => true, 'data' => $data],
    JSON_UNESCAPED_UNICODE
);

$stmt->close();
$conn->close();
