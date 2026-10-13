<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$productID = (int) ($_GET['id'] ?? 0);

if ($productID <= 0) {
    http_response_code(404);
    die('Sản phẩm không hợp lệ.');
}

$stmt = $conn->prepare("
    SELECT
        p.ProductID, p.ProductCode, p.ProductName, p.Description, p.Unit,
        p.Price, p.StockQuantity, c.CategoryName, s.SupplierName
    FROM products p, categories c, suppliers s
    WHERE p.CategoryID = c.CategoryID
      AND p.SupplierID = s.SupplierID
      AND p.IsActive = 1
      AND p.ProductID = ?
");
$stmt->bind_param('i', $productID);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    http_response_code(404);
    die('Không tìm thấy sản phẩm.');
}

$stmt = $conn->prepare("
    SELECT ProductImageID, ImageFile, AltText, IsPrimary
    FROM product_images
    WHERE ProductID = ?
    ORDER BY IsPrimary DESC, SortOrder
");
$stmt->bind_param('i', $productID);
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = $product['ProductName'];

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

$stock = (int) $product['StockQuantity'];
$inCart = (int) ($_SESSION['cart'][$productID] ?? 0);
$flash = $_GET['added'] ?? '';

?>

<main class="container py-4">

    <a href="/products.php" class="btn btn-sm btn-outline-secondary mb-3">&larr; Quay lại danh sách</a>

    <?php if ($flash === '1'): ?>
        <div class="alert alert-success">Đã thêm sản phẩm vào giỏ hàng.</div>
    <?php elseif ($flash === '0'): ?>
        <div class="alert alert-warning">Không thể thêm thêm: số lượng trong giỏ đã đạt mức tồn kho.</div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-md-6">
            <?php if ($images): ?>
                <img
                    id="mainImage"
                    src="/uploads/products/<?= htmlspecialchars($images[0]['ImageFile']) ?>"
                    alt="<?= htmlspecialchars($product['ProductName']) ?>"
                    class="img-fluid border rounded p-2 w-100"
                    style="height: 380px; object-fit: contain;"
                >
                <?php if (count($images) > 1): ?>
                    <div class="d-flex gap-2 mt-2">
                        <?php foreach ($images as $img): ?>
                            <img
                                src="/uploads/products/<?= htmlspecialchars($img['ImageFile']) ?>"
                                alt="<?= htmlspecialchars($product['ProductName']) ?>"
                                class="border rounded p-1"
                                style="width: 80px; height: 80px; object-fit: contain; cursor: pointer;"
                                onclick="document.getElementById('mainImage').src = this.src;"
                            >
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="border rounded p-5 text-center text-muted">Chưa có ảnh</div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <span class="badge bg-secondary mb-2"><?= htmlspecialchars($product['CategoryName']) ?></span>
            <h1 class="h3"><?= htmlspecialchars($product['ProductName']) ?></h1>
            <p class="text-muted mb-1">Mã: <?= htmlspecialchars($product['ProductCode']) ?></p>
            <p class="text-muted">Nhà cung cấp: <?= htmlspecialchars($product['SupplierName']) ?></p>

            <p class="fs-3 fw-bold text-danger">
                <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                <?php if ($product['Unit']): ?>
                    <small class="fs-6 text-muted">/ <?= htmlspecialchars($product['Unit']) ?></small>
                <?php endif; ?>
            </p>

            <p><?= nl2br(htmlspecialchars($product['Description'] ?? '')) ?></p>

            <p>
                Tồn kho:
                <?php if ($stock > 0): ?>
                    <strong><?= $stock ?></strong>
                <?php else: ?>
                    <span class="badge bg-danger">Hết hàng</span>
                <?php endif; ?>
            </p>

            <?php if ($stock > 0): ?>
                <form action="/cart.php" method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $product['ProductID'] ?>">
                    <input type="number" name="quantity" value="1" min="1" max="<?= $stock ?>"
                           class="form-control" style="width: 100px;">
                    <button type="submit" class="btn btn-primary">Thêm vào giỏ</button>
                </form>
                <?php if ($inCart > 0): ?>
                    <p class="text-muted mt-2">Trong giỏ của bạn đang có <?= $inCart ?> sản phẩm này.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
