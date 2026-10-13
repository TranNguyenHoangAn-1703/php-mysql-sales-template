<?php

$pageTitle = 'Quản lý đơn hàng';

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/helpers/orders.php';

$result = $conn->query("
    SELECT
        o.OrderID,
        o.OrderDate,
        o.TotalAmount,
        o.Status,
        c.CustomerName
    FROM orders o, customers c
    WHERE o.CustomerID = c.CustomerID
    ORDER BY o.OrderID DESC
");

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Quản lý đơn hàng</h2>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày đặt</th>
                    <th>Khách hàng</th>
                    <th class="text-end">Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>

            <?php while ($o = $result->fetch_assoc()): ?>
                <tr>
                    <td>#<?= (int) $o['OrderID'] ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($o['OrderDate']))) ?></td>
                    <td><?= htmlspecialchars($o['CustomerName']) ?></td>
                    <td class="text-end"><?= number_format((float) $o['TotalAmount'], 0, ',', '.') ?> đ</td>
                    <td><?= orderStatusBadge($o['Status']) ?></td>
                    <td>
                        <a
                            href="/admin/orders/detail.php?id=<?= (int) $o['OrderID'] ?>"
                            class="btn btn-sm btn-primary"
                        >Xem</a>
                    </td>
                </tr>
            <?php endwhile; ?>

            </tbody>
        </table>
    </div>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
