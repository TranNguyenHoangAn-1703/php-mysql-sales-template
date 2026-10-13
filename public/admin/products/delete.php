<?php

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/helpers/upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products/');
    exit;
}

$productID = (int) ($_POST['id'] ?? 0);

if ($productID <= 0) {
    header('Location: /admin/products/');
    exit;
}

// Lấy tên file ảnh trước khi xóa để dọn file sau khi xóa bản ghi thành công.
$stmt = $conn->prepare("SELECT ImageFile FROM product_images WHERE ProductID = ?");
$stmt->bind_param('i', $productID);
$stmt->execute();
$result = $stmt->get_result();

$files = [];
while ($row = $result->fetch_assoc()) {
    $files[] = $row['ImageFile'];
}
$stmt->close();

$stmt = $conn->prepare("DELETE FROM products WHERE ProductID = ?");
$stmt->bind_param('i', $productID);

try {
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        deleteProductImageFiles($files);
    }
} catch (mysqli_sql_exception $e) {
    // Sản phẩm đã có trong đơn hàng: giữ nguyên dữ liệu.
}

$stmt->close();
$conn->close();

header('Location: /admin/products/');
exit;
