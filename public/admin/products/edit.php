<?php

$pageTitle = 'Sửa sản phẩm';

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/helpers/upload.php';

$error = '';
$message = '';

$productID = (int) ($_GET['id'] ?? 0);

if ($productID <= 0) {
    die('Mã sản phẩm không hợp lệ.');
}

$self = '/admin/products/edit.php?id=' . $productID;

/**
 * Lấy danh sách ảnh của sản phẩm theo thứ tự hiển thị.
 */
function getImages(mysqli $conn, int $productID): array
{
    $stmt = $conn->prepare("
        SELECT ProductImageID, ImageFile, IsPrimary, SortOrder
        FROM product_images
        WHERE ProductID = ?
        ORDER BY SortOrder, ProductImageID
    ");
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/* ------------------------------------------------------------------
 * Các thao tác ảnh (POST) chạy trước phần cập nhật thông tin sản phẩm
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // 1. Xóa một ảnh
    if ($action === 'delete_image') {

        $imageID = (int) ($_POST['image_id'] ?? 0);
        $fileToDelete = null;

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                SELECT ImageFile, IsPrimary
                FROM product_images
                WHERE ProductImageID = ? AND ProductID = ?
                FOR UPDATE
            ");
            $stmt->bind_param('ii', $imageID, $productID);
            $stmt->execute();
            $img = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$img) {
                throw new RuntimeException('Không tìm thấy ảnh.');
            }

            $count = count(getImages($conn, $productID));

            if ($count <= 1) {
                throw new RuntimeException('Sản phẩm phải còn ít nhất 1 ảnh.');
            }

            $stmt = $conn->prepare("DELETE FROM product_images WHERE ProductImageID = ?");
            $stmt->bind_param('i', $imageID);
            $stmt->execute();
            $stmt->close();

            $remaining = getImages($conn, $productID);

            // Nếu vừa xóa ảnh chính thì ảnh đầu tiên còn lại thành ảnh chính.
            if ($img['IsPrimary']) {
                $newPrimary = (int) $remaining[0]['ProductImageID'];
                $stmt = $conn->prepare("
                    UPDATE product_images SET IsPrimary = 1 WHERE ProductImageID = ?
                ");
                $stmt->bind_param('i', $newPrimary);
                $stmt->execute();
                $stmt->close();
            }

            // Đánh lại SortOrder 1..n
            $stmt = $conn->prepare("
                UPDATE product_images SET SortOrder = ? WHERE ProductImageID = ?
            ");
            foreach ($remaining as $i => $r) {
                $order = $i + 1;
                $rid = (int) $r['ProductImageID'];
                $stmt->bind_param('ii', $order, $rid);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            $fileToDelete = $img['ImageFile'];

        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể xóa ảnh.';
        }

        if ($fileToDelete !== null) {
            deleteProductImageFiles([$fileToDelete]);
            header('Location: ' . $self);
            exit;
        }
    }

    // 2. Đặt ảnh chính
    elseif ($action === 'set_primary_image') {

        $imageID = (int) ($_POST['image_id'] ?? 0);

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                SELECT ProductImageID FROM product_images
                WHERE ProductImageID = ? AND ProductID = ?
            ");
            $stmt->bind_param('ii', $imageID, $productID);
            $stmt->execute();
            $found = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$found) {
                throw new RuntimeException('Không tìm thấy ảnh.');
            }

            $stmt = $conn->prepare("UPDATE product_images SET IsPrimary = 0 WHERE ProductID = ?");
            $stmt->bind_param('i', $productID);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE product_images SET IsPrimary = 1 WHERE ProductImageID = ?");
            $stmt->bind_param('i', $imageID);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header('Location: ' . $self);
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Không thể đặt ảnh chính.';
        }
    }

    // 3. Thêm ảnh
    elseif ($action === 'add_images') {

        $files = collectUploadedFiles('images');
        $current = count(getImages($conn, $productID));

        if (count($files) < 1) {
            $error = 'Vui lòng chọn ít nhất 1 ảnh.';
        } elseif ($current + count($files) > MAX_IMAGES_PER_PRODUCT) {
            $error = 'Mỗi sản phẩm tối đa ' . MAX_IMAGES_PER_PRODUCT . ' ảnh.';
        } else {

            [$savedFiles, $uploadError] = saveProductImages($files);

            if ($uploadError !== '') {
                $error = $uploadError;
            } else {

                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare("
                        SELECT ProductName FROM products WHERE ProductID = ?
                    ");
                    $stmt->bind_param('i', $productID);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $altText = $row['ProductName'] ?? '';

                    $stmt = $conn->prepare("
                        SELECT COALESCE(MAX(SortOrder), 0) AS MaxSort
                        FROM product_images WHERE ProductID = ?
                    ");
                    $stmt->bind_param('i', $productID);
                    $stmt->execute();
                    $sort = (int) $stmt->get_result()->fetch_assoc()['MaxSort'];
                    $stmt->close();

                    $stmt = $conn->prepare("
                        INSERT INTO product_images
                            (ProductID, ImageFile, AltText, IsPrimary, SortOrder)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    foreach ($savedFiles as $i => $fileName) {
                        $sort++;
                        $isPrimary = ($current === 0 && $i === 0) ? 1 : 0;
                        $stmt->bind_param(
                            'issii',
                            $productID, $fileName, $altText, $isPrimary, $sort
                        );
                        $stmt->execute();
                    }

                    $stmt->close();
                    $conn->commit();

                    header('Location: ' . $self);
                    exit;

                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    deleteProductImageFiles($savedFiles);
                    $error = 'Không thể thêm ảnh.';
                }
            }
        }
    }
}

/* ------------------------------------------------------------------
 * Tải sản phẩm
 * ------------------------------------------------------------------ */
$stmt = $conn->prepare("
    SELECT ProductID, ProductCode, ProductName, Description, Unit,
           Price, StockQuantity, IsActive, SupplierID, CategoryID
    FROM products
    WHERE ProductID = ?
");
$stmt->bind_param('i', $productID);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die('Không tìm thấy sản phẩm.');
}

/* ------------------------------------------------------------------
 * Cập nhật thông tin sản phẩm
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_info') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock_quantity'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    if ($productCode === '' || $productName === '') {
        $error = 'Mã và tên sản phẩm không được để trống.';
    } elseif ($price < 0 || $stock < 0) {
        $error = 'Giá và tồn kho không được âm.';
    } elseif ($categoryID <= 0 || $supplierID <= 0) {
        $error = 'Vui lòng chọn danh mục và nhà cung cấp.';
    } else {

        $stmt = $conn->prepare("
            UPDATE products
            SET ProductCode = ?, ProductName = ?, Description = ?, Unit = ?,
                Price = ?, StockQuantity = ?, IsActive = ?,
                SupplierID = ?, CategoryID = ?
            WHERE ProductID = ?
        ");
        $stmt->bind_param(
            'ssssdiiiii',
            $productCode, $productName, $description, $unit,
            $price, $stock, $isActive, $supplierID, $categoryID, $productID
        );

        try {
            $stmt->execute();
            $stmt->close();
            header('Location: /admin/products/');
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = 'Không thể cập nhật sản phẩm. Mã sản phẩm có thể đã tồn tại.';
        }
    }
}

$categories = $conn->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName");
$suppliers = $conn->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName");
$images = getImages($conn, $productID);

// Giá trị hiển thị: ưu tiên dữ liệu vừa nhập nếu form thông tin bị lỗi.
$isInfoPost = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_info';
$val = function (string $postKey, string $dbKey) use ($isInfoPost, $product): string {
    return htmlspecialchars((string) ($isInfoPost ? ($_POST[$postKey] ?? '') : ($product[$dbKey] ?? '')));
};
$selCategory = $isInfoPost ? (int) ($_POST['category_id'] ?? 0) : (int) $product['CategoryID'];
$selSupplier = $isInfoPost ? (int) ($_POST['supplier_id'] ?? 0) : (int) $product['SupplierID'];
$checked = $isInfoPost ? isset($_POST['is_active']) : (bool) $product['IsActive'];

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Sửa sản phẩm</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="update_info">

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="productCode" class="form-label">Mã sản phẩm</label>
                <input type="text" class="form-control" id="productCode" name="product_code"
                       value="<?= $val('product_code', 'ProductCode') ?>" required>
            </div>
            <div class="col-md-8 mb-3">
                <label for="productName" class="form-label">Tên sản phẩm</label>
                <input type="text" class="form-control" id="productName" name="product_name"
                       value="<?= $val('product_name', 'ProductName') ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= $val('description', 'Description') ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="unit" class="form-label">Đơn vị</label>
                <input type="text" class="form-control" id="unit" name="unit"
                       value="<?= $val('unit', 'Unit') ?>">
            </div>
            <div class="col-md-5 mb-3">
                <label for="price" class="form-label">Giá</label>
                <input type="number" class="form-control" id="price" name="price" min="0" step="1000"
                       value="<?= $val('price', 'Price') ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="stock" class="form-label">Tồn kho</label>
                <input type="number" class="form-control" id="stock" name="stock_quantity" min="0"
                       value="<?= $val('stock_quantity', 'StockQuantity') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="category" class="form-label">Danh mục</label>
                <select class="form-select" id="category" name="category_id" required>
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= (int) $c['CategoryID'] ?>"
                            <?= $selCategory === (int) $c['CategoryID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['CategoryName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="supplier" class="form-label">Nhà cung cấp</label>
                <select class="form-select" id="supplier" name="supplier_id" required>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?= (int) $s['SupplierID'] ?>"
                            <?= $selSupplier === (int) $s['SupplierID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['SupplierName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="isActive" name="is_active"
                   <?= $checked ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Đang bán</label>
        </div>

        <button type="submit" class="btn btn-warning">Cập nhật</button>
        <a href="/admin/products/" class="btn btn-secondary">Hủy</a>
    </form>

    <hr class="my-4">

    <h4 class="mb-3">Ảnh sản phẩm (<?= count($images) ?>/<?= MAX_IMAGES_PER_PRODUCT ?>)</h4>

    <div class="row g-3 mb-4">
        <?php foreach ($images as $img): ?>
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <img
                        src="/uploads/products/<?= htmlspecialchars($img['ImageFile']) ?>"
                        class="card-img-top p-2"
                        alt="<?= htmlspecialchars($product['ProductName']) ?>"
                        style="height: 160px; object-fit: contain;"
                    >
                    <div class="card-body">
                        <?php if ($img['IsPrimary']): ?>
                            <span class="badge bg-success mb-2">Ảnh chính</span>
                        <?php else: ?>
                            <form method="post" class="mb-2">
                                <input type="hidden" name="action" value="set_primary_image">
                                <input type="hidden" name="image_id" value="<?= (int) $img['ProductImageID'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                    Đặt làm ảnh chính
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="post"
                              onsubmit="return confirm('Xóa ảnh này?');">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?= (int) $img['ProductImageID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100">Xóa ảnh</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($images) < MAX_IMAGES_PER_PRODUCT): ?>
        <form method="post" enctype="multipart/form-data" class="mb-5">
            <input type="hidden" name="action" value="add_images">
            <div class="mb-3">
                <label for="newImages" class="form-label">
                    Thêm ảnh (còn tối đa <?= MAX_IMAGES_PER_PRODUCT - count($images) ?> ảnh)
                </label>
                <input type="file" class="form-control" id="newImages" name="images[]"
                       accept="image/jpeg,image/png,image/webp" multiple required>
            </div>
            <button type="submit" class="btn btn-primary">Tải ảnh lên</button>
        </form>
    <?php endif; ?>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
