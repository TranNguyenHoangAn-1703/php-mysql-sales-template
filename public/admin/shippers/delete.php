<?php

require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/shippers/');
    exit;
}

$shipperID = isset($_POST['id'])
    ? (int) $_POST['id']
    : 0;

if ($shipperID <= 0) {
    header('Location: /admin/shippers/');
    exit;
}

$stmt = $conn->prepare("
    DELETE FROM shippers
    WHERE ShipperID = ?
");

$stmt->bind_param('i', $shipperID);

try {
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    // Người giao hàng đã được đơn hàng tham chiếu: giữ nguyên dữ liệu.
}

$stmt->close();
$conn->close();

header('Location: /admin/shippers/');
exit;
