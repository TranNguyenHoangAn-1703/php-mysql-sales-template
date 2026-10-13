<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Sản phẩm';

$perPage = 6;

$keyword = trim($_GET['keyword'] ?? '');
$categoryID = (int) ($_GET['category_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));

$categories = $conn->query("SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName");

/* Điều kiện lọc dùng chung cho câu đếm và câu lấy dữ liệu */
$where = "p.IsActive = 1 AND p.CategoryID = c.CategoryID";
$types = '';
$params = [];

if ($keyword !== '') {
    $where .= " AND p.ProductName LIKE ?";
    $types .= 's';
    $params[] = '%' . $keyword . '%';
}

if ($categoryID > 0) {
    $where .= " AND p.CategoryID = ?";
    $types .= 'i';
    $params[] = $categoryID;
}

/* Đếm tổng số sản phẩm */
$stmt = $conn->prepare("SELECT COUNT(*) AS Total FROM products p, categories c WHERE $where");
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalProducts = (int) $stmt->get_result()->fetch_assoc()['Total'];
$stmt->close();

$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* Lấy sản phẩm của trang hiện tại */
$sql = "
    SELECT
        p.ProductID, p.ProductName, p.Price, p.Unit, p.StockQuantity,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
            ORDER BY pi.IsPrimary DESC, pi.SortOrder
            LIMIT 1
        ) AS ImageFile
    FROM products p, categories c
    WHERE $where
    ORDER BY p.ProductID
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$pageTypes = $types . 'ii';
$pageParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($pageTypes, ...$pageParams);
$stmt->execute();
$products = $stmt->get_result();

/* Giữ bộ lọc khi chuyển trang */
$buildPageUrl = function (int $p) use ($keyword, $categoryID): string {
    $query = ['page' => $p];

    if ($keyword !== '') {
        $query['keyword'] = $keyword;
    }
    if ($categoryID > 0) {
        $query['category_id'] = $categoryID;
    }

    return '/products.php?' . http_build_query($query);
};

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-4">

    <h1 class="mb-4">Sản phẩm</h1>

    <form method="get" class="row g-2 mb-4">
        <div class="col-md-5">
            <input type="text" name="keyword" class="form-control"
                   placeholder="Tìm theo tên sản phẩm"
                   value="<?= htmlspecialchars($keyword) ?>">
        </div>
        <div class="col-md-4">
            <select name="category_id" class="form-select">
                <option value="0">Tất cả danh mục</option>
                <?php while ($c = $categories->fetch_assoc()): ?>
                    <option value="<?= (int) $c['CategoryID'] ?>"
                        <?= $categoryID === (int) $c['CategoryID'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['CategoryName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Lọc</button>
            <a href="/products.php" class="btn btn-outline-secondary">Xóa lọc</a>
        </div>
    </form>

    <?php if ($totalProducts === 0): ?>
        <div class="alert alert-info">Không tìm thấy sản phẩm phù hợp.</div>
    <?php else: ?>

        <div class="row g-4">
            <?php while ($p = $products->fetch_assoc()): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="card h-100">
                        <?php if ($p['ImageFile']): ?>
                            <img
                                src="/uploads/products/<?= htmlspecialchars($p['ImageFile']) ?>"
                                class="card-img-top p-3"
                                alt="<?= htmlspecialchars($p['ProductName']) ?>"
                                style="height: 220px; object-fit: contain;"
                            >
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-secondary align-self-start mb-2">
                                <?= htmlspecialchars($p['CategoryName']) ?>
                            </span>
                            <h5 class="card-title"><?= htmlspecialchars($p['ProductName']) ?></h5>
                            <p class="card-text fw-bold text-danger">
                                <?= number_format((float) $p['Price'], 0, ',', '.') ?> đ
                            </p>
                            <a href="/product-detail.php?id=<?= (int) $p['ProductID'] ?>"
                               class="btn btn-outline-primary mt-auto">Xem chi tiết</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Phân trang">
                <ul class="pagination justify-content-center">

                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($buildPageUrl(max(1, $page - 1))) ?>">Trước</a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($buildPageUrl($i)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($buildPageUrl(min($totalPages, $page + 1))) ?>">Sau</a>
                    </li>

                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php

$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
