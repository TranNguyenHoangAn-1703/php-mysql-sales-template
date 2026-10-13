<?php

$pageTitle = 'Quản lý người giao hàng';

require_once '/var/www/src/config/database.php';

$result = $conn->query("
    SELECT ShipperID, ShipperName, Phone
    FROM shippers
    ORDER BY ShipperID
");

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2>Quản lý người giao hàng</h2>

        <a href="/admin/shippers/create.php" class="btn btn-primary">
            Thêm người giao hàng
        </a>

    </div>

    <div class="table-responsive">

        <table class="table table-bordered table-striped">

            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Tên</th>
                    <th>Điện thoại</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

            <?php while ($shipper = $result->fetch_assoc()): ?>

                <tr>
                    <td><?= (int) $shipper['ShipperID'] ?></td>
                    <td><?= htmlspecialchars($shipper['ShipperName']) ?></td>
                    <td><?= htmlspecialchars($shipper['Phone'] ?? '') ?></td>
                    <td>

                        <a
                            href="/admin/shippers/edit.php?id=<?= (int) $shipper['ShipperID'] ?>"
                            class="btn btn-sm btn-warning"
                        >
                            Sửa
                        </a>

                        <form
                            action="/admin/shippers/delete.php"
                            method="post"
                            class="d-inline"
                            onsubmit="return confirm('Bạn có chắc muốn xóa người giao hàng này?');"
                        >
                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $shipper['ShipperID'] ?>"
                            >

                            <button type="submit" class="btn btn-sm btn-danger">
                                Xóa
                            </button>
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
