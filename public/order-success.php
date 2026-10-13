<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$orderID = (int) ($_GET['id'] ?? 0);

if ($orderID <= 0) {
    header('Location: /');
    exit;
}

$stmt = $conn->prepare("
    SELECT o.OrderID, o.OrderDate, o.TotalAmount, c.CustomerName
    FROM orders o, customers c
    WHERE o.CustomerID = c.CustomerID
      AND o.OrderID = ?
");
$stmt->bind_param('i', $orderID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: /');
    exit;
}

$stmt = $conn->prepare("
    SELECT p.ProductName, od.Quantity, od.UnitPrice
    FROM orderdetail od, products p
    WHERE od.ProductID = p.ProductID
      AND od.OrderID = ?
    ORDER BY od.OrderDetailID
");
$stmt->bind_param('i', $orderID);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Đặt hàng thành công';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-4">

    <div class="alert alert-success">
        <h1 class="h4">Đặt hàng thành công</h1>
        Mã đơn hàng của bạn là <strong>#<?= (int) $order['OrderID'] ?></strong>.
        Cảm ơn <?= htmlspecialchars($order['CustomerName']) ?>.
    </div>

    <table class="table">
        <thead class="table-dark">
            <tr>
                <th>Sản phẩm</th>
                <th class="text-end">Đơn giá</th>
                <th class="text-end">Số lượng</th>
                <th class="text-end">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['ProductName']) ?></td>
                    <td class="text-end"><?= number_format((float) $l['UnitPrice'], 0, ',', '.') ?> đ</td>
                    <td class="text-end"><?= (int) $l['Quantity'] ?></td>
                    <td class="text-end"><?= number_format((float) $l['UnitPrice'] * (int) $l['Quantity'], 0, ',', '.') ?> đ</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Tổng cộng</th>
                <th class="text-end"><?= number_format((float) $order['TotalAmount'], 0, ',', '.') ?> đ</th>
            </tr>
        </tfoot>
    </table>

    <a href="/products.php" class="btn btn-primary">Tiếp tục mua sắm</a>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
