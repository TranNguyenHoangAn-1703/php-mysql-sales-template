<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$notice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);

/**
 * Lấy tồn kho của một sản phẩm đang bán, null nếu không tồn tại.
 */
function getStock(mysqli $conn, int $productID): ?int
{
    $stmt = $conn->prepare("
        SELECT StockQuantity FROM products
        WHERE ProductID = ? AND IsActive = 1
    ");
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['StockQuantity'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $productID = (int) ($_POST['product_id'] ?? 0);

    if ($action === 'add') {

        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $stock = getStock($conn, $productID);

        if ($stock === null || $stock <= 0) {
            $_SESSION['cart_notice'] = 'Sản phẩm không còn để bán.';
        } else {
            $newQty = ($_SESSION['cart'][$productID] ?? 0) + $quantity;

            if ($newQty > $stock) {
                $newQty = $stock;
                $_SESSION['cart_notice'] = 'Số lượng đã được giới hạn theo tồn kho.';
            }

            $_SESSION['cart'][$productID] = $newQty;
        }

    } elseif ($action === 'update') {

        $quantity = (int) ($_POST['quantity'] ?? 0);

        if (isset($_SESSION['cart'][$productID])) {
            $stock = getStock($conn, $productID);

            if ($quantity <= 0 || $stock === null || $stock <= 0) {
                unset($_SESSION['cart'][$productID]);
            } else {
                if ($quantity > $stock) {
                    $quantity = $stock;
                    $_SESSION['cart_notice'] = 'Số lượng đã được giới hạn theo tồn kho.';
                }
                $_SESSION['cart'][$productID] = $quantity;
            }
        }

    } elseif ($action === 'remove') {

        unset($_SESSION['cart'][$productID]);

    } elseif ($action === 'clear') {

        $_SESSION['cart'] = [];
    }

    header('Location: /cart.php');
    exit;
}

/* Tải thông tin các sản phẩm trong giỏ */
$items = [];
$total = 0.0;

if ($_SESSION['cart']) {

    $ids = array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT
            p.ProductID, p.ProductName, p.Price, p.StockQuantity,
            (
                SELECT pi.ImageFile FROM product_images pi
                WHERE pi.ProductID = p.ProductID
                ORDER BY pi.IsPrimary DESC, pi.SortOrder
                LIMIT 1
            ) AS ImageFile
        FROM products p
        WHERE p.IsActive = 1 AND p.ProductID IN ($placeholders)
    ");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $qty = (int) $_SESSION['cart'][$row['ProductID']];
        $row['Quantity'] = $qty;
        $row['LineTotal'] = (float) $row['Price'] * $qty;
        $total += $row['LineTotal'];
        $items[] = $row;
    }

    $stmt->close();

    // Loại khỏi giỏ những sản phẩm không còn bán
    $found = array_map(fn($r) => (int) $r['ProductID'], $items);
    foreach ($ids as $id) {
        if (!in_array($id, $found, true)) {
            unset($_SESSION['cart'][$id]);
        }
    }
}

$pageTitle = 'Giỏ hàng';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-4">

    <h1 class="mb-4">Giỏ hàng</h1>

    <?php if ($notice !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <?php if (!$items): ?>

        <div class="alert alert-info">Giỏ hàng đang trống.</div>
        <a href="/products.php" class="btn btn-primary">Tiếp tục mua sắm</a>

    <?php else: ?>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Sản phẩm</th>
                        <th class="text-end">Đơn giá</th>
                        <th style="width: 180px;">Số lượng</th>
                        <th class="text-end">Thành tiền</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($item['ImageFile']): ?>
                                    <img src="/uploads/products/<?= htmlspecialchars($item['ImageFile']) ?>"
                                         alt="<?= htmlspecialchars($item['ProductName']) ?>"
                                         style="width: 64px; height: 64px; object-fit: contain;">
                                <?php endif; ?>
                                <a href="/product-detail.php?id=<?= (int) $item['ProductID'] ?>">
                                    <?= htmlspecialchars($item['ProductName']) ?>
                                </a>
                            </div>
                        </td>
                        <td class="text-end"><?= number_format((float) $item['Price'], 0, ',', '.') ?> đ</td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= (int) $item['ProductID'] ?>">
                                <input type="number" name="quantity" min="0"
                                       max="<?= (int) $item['StockQuantity'] ?>"
                                       value="<?= (int) $item['Quantity'] ?>"
                                       class="form-control form-control-sm">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Cập nhật</button>
                            </form>
                        </td>
                        <td class="text-end"><?= number_format($item['LineTotal'], 0, ',', '.') ?> đ</td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= (int) $item['ProductID'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Tổng cộng</th>
                        <th class="text-end"><?= number_format($total, 0, ',', '.') ?> đ</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <div class="d-flex gap-2">
                <a href="/products.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
                <form method="post" onsubmit="return confirm('Xóa toàn bộ giỏ hàng?');">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline-danger">Xóa giỏ hàng</button>
                </form>
            </div>
            <a href="/checkout.php" class="btn btn-success">Tiến hành đặt hàng</a>
        </div>

    <?php endif; ?>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
