<?php

$pageTitle = 'Sửa người giao hàng';

require_once '/var/www/src/config/database.php';

$error = '';

$shipperID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($shipperID <= 0) {
    die('Mã người giao hàng không hợp lệ.');
}

$stmt = $conn->prepare("
    SELECT ShipperID, ShipperName, Phone
    FROM shippers
    WHERE ShipperID = ?
");

$stmt->bind_param('i', $shipperID);
$stmt->execute();

$result = $stmt->get_result();
$shipper = $result->fetch_assoc();

$stmt->close();

if (!$shipper) {
    die('Không tìm thấy người giao hàng.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $shipperName = trim($_POST['shipper_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($shipperName === '') {

        $error = 'Tên người giao hàng không được để trống.';

    } else {

        $stmt = $conn->prepare("
            UPDATE shippers
            SET ShipperName = ?, Phone = ?
            WHERE ShipperID = ?
        ");

        $stmt->bind_param('ssi', $shipperName, $phone, $shipperID);

        if ($stmt->execute()) {
            header('Location: /admin/shippers/');
            exit;
        }

        $error = 'Không thể cập nhật người giao hàng.';
        $stmt->close();
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Sửa người giao hàng</h2>

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
                value="<?= htmlspecialchars(
                    $_POST['shipper_name'] ?? $shipper['ShipperName']
                ) ?>"
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
                value="<?= htmlspecialchars(
                    $_POST['phone'] ?? ($shipper['Phone'] ?? '')
                ) ?>"
            >
        </div>

        <button type="submit" class="btn btn-warning">Cập nhật</button>
        <a href="/admin/shippers/" class="btn btn-secondary">Hủy</a>

    </form>

</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
