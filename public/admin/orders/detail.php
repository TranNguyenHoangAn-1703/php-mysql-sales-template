<?php

$pageTitle = 'Chi tiết đơn hàng';

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/helpers/orders.php';

$error = '';

$orderID = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($orderID <= 0) {
    die('Mã đơn hàng không hợp lệ.');
}

/* ------------------------------------------------------------------
 * Cập nhật trạng thái
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newStatus = $_POST['new_status'] ?? '';
    $transitions = orderStatusTransitions();

    $conn->begin_transaction();

    try {
        // Khóa đơn hàng và đọc lại trạng thái hiện tại trên server.
        $stmt = $conn->prepare("SELECT Status FROM orders WHERE OrderID = ? FOR UPDATE");
        $stmt->bind_param('i', $orderID);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('Không tìm thấy đơn hàng.');
        }

        $current = $row['Status'];

        if (!in_array($newStatus, $transitions[$current] ?? [], true)) {
            throw new RuntimeException('Không thể chuyển trạng thái đơn hàng theo cách này.');
        }

        // Hủy đơn: trả hàng về kho.
        if ($newStatus === 'Cancelled') {
            $stmt = $conn->prepare("
                SELECT ProductID, Quantity FROM orderdetail WHERE OrderID = ?
            ");
            $stmt->bind_param('i', $orderID);
            $stmt->execute();
            $lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $restore = $conn->prepare("
                UPDATE products
                SET StockQuantity = StockQuantity + ?
                WHERE ProductID = ?
            ");

            foreach ($lines as $line) {
                $qty = (int) $line['Quantity'];
                $pid = (int) $line['ProductID'];
                $restore->bind_param('ii', $qty, $pid);
                $restore->execute();
            }

            $restore->close();
        }

        $stmt = $conn->prepare("UPDATE orders SET Status = ? WHERE OrderID = ?");
        $stmt->bind_param('si', $newStatus, $orderID);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        header('Location: /admin/orders/detail.php?id=' . $orderID);
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Không thể cập nhật trạng thái đơn hàng.';
    }
}

/* ------------------------------------------------------------------
 * Tải đơn hàng
 * ------------------------------------------------------------------ */
$stmt = $conn->prepare("
    SELECT
        o.OrderID, o.OrderDate, o.TotalAmount, o.Status,
        c.CustomerName, c.Phone, c.Email, c.Address, c.City
    FROM orders o, customers c
    WHERE o.CustomerID = c.CustomerID
      AND o.OrderID = ?
");
$stmt->bind_param('i', $orderID);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Không tìm thấy đơn hàng.');
}

$stmt = $conn->prepare("
    SELECT p.ProductCode, p.ProductName, od.Quantity, od.UnitPrice
    FROM orderdetail od, products p
    WHERE od.ProductID = p.ProductID
      AND od.OrderID = ?
    ORDER BY od.OrderDetailID
");
$stmt->bind_param('i', $orderID);
$stmt->execute();
$details = $stmt->get_result();

$nextStatuses = orderStatusTransitions()[$order['Status']] ?? [];
$labels = orderStatusLabels();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Đơn hàng #<?= (int) $order['OrderID'] ?></h2>
        <a href="/admin/orders/" class="btn btn-secondary">Quay lại</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Khách hàng</div>
                <div class="card-body">
                    <p class="mb-1"><strong><?= htmlspecialchars($order['CustomerName']) ?></strong></p>
                    <p class="mb-1">Điện thoại: <?= htmlspecialchars($order['Phone'] ?? '') ?></p>
                    <p class="mb-1">Email: <?= htmlspecialchars($order['Email'] ?? '') ?></p>
                    <p class="mb-0">Địa chỉ: <?= htmlspecialchars(trim(($order['Address'] ?? '') . ' ' . ($order['City'] ?? ''))) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Đơn hàng</div>
                <div class="card-body">
                    <p class="mb-1">Ngày đặt: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['OrderDate']))) ?></p>
                    <p class="mb-2">Trạng thái: <?= orderStatusBadge($order['Status']) ?></p>

                    <?php if ($nextStatuses): ?>
                        <form method="post" class="d-flex gap-2 align-items-center"
                              onsubmit="return confirm('Cập nhật trạng thái đơn hàng?');">
                            <input type="hidden" name="id" value="<?= (int) $order['OrderID'] ?>">
                            <select name="new_status" class="form-select form-select-sm w-auto">
                                <?php foreach ($nextStatuses as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>">
                                        <?= htmlspecialchars($labels[$s][0] ?? $s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-warning">Cập nhật</button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0">Đơn hàng đã kết thúc, không thể đổi trạng thái.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Số lượng</th>
                    <th class="text-end">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($d = $details->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($d['ProductCode']) ?></td>
                    <td><?= htmlspecialchars($d['ProductName']) ?></td>
                    <td class="text-end"><?= number_format((float) $d['UnitPrice'], 0, ',', '.') ?> đ</td>
                    <td class="text-end"><?= (int) $d['Quantity'] ?></td>
                    <td class="text-end"><?= number_format((float) $d['UnitPrice'] * (int) $d['Quantity'], 0, ',', '.') ?> đ</td>
                </tr>
            <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Tổng cộng</th>
                    <th class="text-end"><?= number_format((float) $order['TotalAmount'], 0, ',', '.') ?> đ</th>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

<?php

$stmt->close();

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
