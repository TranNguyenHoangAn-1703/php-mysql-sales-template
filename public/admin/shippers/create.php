<?php

$pageTitle = 'Thêm người giao hàng';

require_once '/var/www/src/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $shipperName = trim($_POST['shipper_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($shipperName === '') {

        $error = 'Tên người giao hàng không được để trống.';

    } else {

        $stmt = $conn->prepare("
            INSERT INTO shippers (ShipperName, Phone)
            VALUES (?, ?)
        ");

        $stmt->bind_param('ss', $shipperName, $phone);

        if ($stmt->execute()) {
            header('Location: /admin/shippers/');
            exit;
        }

        $error = 'Không thể thêm người giao hàng.';
        $stmt->close();
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Thêm người giao hàng</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <div class="mb-3">
            <label for="shipperName" class="form-label">Tên</label>
            <input
                type="text"
                class="form-control"
                id="shipperName"
                name="shipper_name"
                value="<?= htmlspecialchars($_POST['shipper_name'] ?? '') ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Điện thoại</label>
            <input
                type="text"
                class="form-control"
                id="phone"
                name="phone"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary">Lưu</button>
        <a href="/admin/shippers/" class="btn btn-secondary">Hủy</a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';
