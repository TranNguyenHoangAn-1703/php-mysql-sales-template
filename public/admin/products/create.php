<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/helpers/upload.php';

$error = '';

$categories = $conn->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName");
$suppliers = $conn->query("SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock_quantity'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $files = collectUploadedFiles('images');

    if ($productCode === '' || $productName === '') {
        $error = 'Mã và tên sản phẩm không được để trống.';
    } elseif ($price < 0 || $stock < 0) {
        $error = 'Giá và tồn kho không được âm.';
    } elseif ($categoryID <= 0 || $supplierID <= 0) {
        $error = 'Vui lòng chọn danh mục và nhà cung cấp.';
    } elseif (count($files) < 1 || count($files) > MAX_IMAGES_PER_PRODUCT) {
        $error = 'Vui lòng chọn từ 1 đến ' . MAX_IMAGES_PER_PRODUCT . ' ảnh.';
    } else {

        [$savedFiles, $uploadError] = saveProductImages($files);

        if ($uploadError !== '') {
            $error = $uploadError;
        } else {

            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("
                    INSERT INTO products
                        (ProductCode, ProductName, Description, Unit, Price,
                         StockQuantity, IsActive, SupplierID, CategoryID)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'ssssdiiii',
                    $productCode, $productName, $description, $unit,
                    $price, $stock, $isActive, $supplierID, $categoryID
                );
                $stmt->execute();
                $productID = $conn->insert_id;
                $stmt->close();

                $imgStmt = $conn->prepare("
                    INSERT INTO product_images
                        (ProductID, ImageFile, AltText, IsPrimary, SortOrder)
                    VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($savedFiles as $index => $fileName) {
                    $isPrimary = $index === 0 ? 1 : 0;
                    $sortOrder = $index + 1;
                    $imgStmt->bind_param(
                        'issii',
                        $productID, $fileName, $productName, $isPrimary, $sortOrder
                    );
                    $imgStmt->execute();
                }

                $imgStmt->close();
                $conn->commit();

                header('Location: /admin/products/');
                exit;

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                deleteProductImageFiles($savedFiles);
                $error = 'Không thể thêm sản phẩm. Mã sản phẩm có thể đã tồn tại.';
            }
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Thêm sản phẩm</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="productCode" class="form-label">Mã sản phẩm</label>
                <input type="text" class="form-control" id="productCode" name="product_code"
                       value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>" required>
            </div>
            <div class="col-md-8 mb-3">
                <label for="productName" class="form-label">Tên sản phẩm</label>
                <input type="text" class="form-control" id="productName" name="product_name"
                       value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Mô tả</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label for="unit" class="form-label">Đơn vị</label>
                <input type="text" class="form-control" id="unit" name="unit"
                       value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>">
            </div>
            <div class="col-md-5 mb-3">
                <label for="price" class="form-label">Giá</label>
                <input type="number" class="form-control" id="price" name="price" min="0" step="1000"
                       value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="stock" class="form-label">Tồn kho</label>
                <input type="number" class="form-control" id="stock" name="stock_quantity" min="0"
                       value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="category" class="form-label">Danh mục</label>
                <select class="form-select" id="category" name="category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    <?php while ($c = $categories->fetch_assoc()): ?>
                        <option value="<?= (int) $c['CategoryID'] ?>"
                            <?= (int) ($_POST['category_id'] ?? 0) === (int) $c['CategoryID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['CategoryName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="supplier" class="form-label">Nhà cung cấp</label>
                <select class="form-select" id="supplier" name="supplier_id" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?= (int) $s['SupplierID'] ?>"
                            <?= (int) ($_POST['supplier_id'] ?? 0) === (int) $s['SupplierID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['SupplierName']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label for="images" class="form-label">Ảnh sản phẩm (1 đến 4 ảnh, tối đa 2 MB mỗi ảnh)</label>
            <input type="file" class="form-control" id="images" name="images[]"
                   accept="image/jpeg,image/png,image/webp" multiple required>
            <div class="form-text">Ảnh đầu tiên sẽ là ảnh chính.</div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="isActive" name="is_active"
                   <?= $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Đang bán</label>
        </div>

        <button type="submit" class="btn btn-primary">Lưu</button>
        <a href="/admin/products/" class="btn btn-secondary">Hủy</a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
