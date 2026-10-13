<?php

$pageTitle = 'Quản lý sản phẩm';

require_once '/var/www/src/config/database.php';

$result = $conn->query("
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
        p.StockQuantity,
        p.IsActive,
        c.CategoryName,
        s.SupplierName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
            ORDER BY pi.IsPrimary DESC, pi.SortOrder
            LIMIT 1
        ) AS ImageFile
    FROM products p, categories c, suppliers s
    WHERE p.CategoryID = c.CategoryID
      AND p.SupplierID = s.SupplierID
    ORDER BY p.ProductID
");

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Quản lý sản phẩm</h2>
        <a href="/admin/products/create.php" class="btn btn-primary">Thêm sản phẩm</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Mã</th>
                    <th>Tên</th>
                    <th>Danh mục</th>
                    <th>Nhà cung cấp</th>
                    <th class="text-end">Giá</th>
                    <th class="text-end">Tồn kho</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>

            <?php while ($p = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int) $p['ProductID'] ?></td>
                    <td style="width: 80px;">
                        <?php if ($p['ImageFile']): ?>
                            <img
                                src="/uploads/products/<?= htmlspecialchars($p['ImageFile']) ?>"
                                alt="<?= htmlspecialchars($p['ProductName']) ?>"
                                style="width: 64px; height: 64px; object-fit: contain;"
                            >
                        <?php else: ?>
                            <span class="text-muted">Chưa có</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['ProductCode']) ?></td>
                    <td><?= htmlspecialchars($p['ProductName']) ?></td>
                    <td><?= htmlspecialchars($p['CategoryName']) ?></td>
                    <td><?= htmlspecialchars($p['SupplierName']) ?></td>
                    <td class="text-end"><?= number_format((float) $p['Price'], 0, ',', '.') ?> đ</td>
                    <td class="text-end"><?= (int) $p['StockQuantity'] ?></td>
                    <td>
                        <?php if ($p['IsActive']): ?>
                            <span class="badge bg-success">Đang bán</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Ẩn</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a
                            href="/admin/products/edit.php?id=<?= (int) $p['ProductID'] ?>"
                            class="btn btn-sm btn-warning"
                        >Sửa</a>

                        <form
                            action="/admin/products/delete.php"
                            method="post"
                            class="d-inline"
                            onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');"
                        >
                            <input type="hidden" name="id" value="<?= (int) $p['ProductID'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                        </form>
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
