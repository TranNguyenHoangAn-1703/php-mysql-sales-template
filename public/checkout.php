<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: /cart.php');
    exit;
}

$error = '';
$loggedInID = isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : 0;
$account = null;

if ($loggedInID > 0) {
    $stmt = $conn->prepare("
        SELECT CustomerID, CustomerName, Email, Phone, Address, City
        FROM customers WHERE CustomerID = ?
    ");
    $stmt->bind_param('i', $loggedInID);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$account) {
        unset($_SESSION['customer_id'], $_SESSION['customer_name']);
        $loggedInID = 0;
    }
}

$form = [
    'customer_name' => $account['CustomerName'] ?? '',
    'email'         => $account['Email'] ?? '',
    'phone'         => $account['Phone'] ?? '',
    'address'       => $account['Address'] ?? '',
    'city'          => $account['City'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['address'] = trim($_POST['address'] ?? '');
    $form['city'] = trim($_POST['city'] ?? '');

    if (!$account) {
        $form['customer_name'] = trim($_POST['customer_name'] ?? '');
        $form['email'] = trim($_POST['email'] ?? '');
    }

    if ($form['customer_name'] === '' || $form['phone'] === '' || $form['address'] === '') {
        $error = 'Vui lòng nhập họ tên, số điện thoại và địa chỉ giao hàng.';
    } else {

        $conn->begin_transaction();

        try {
            $cart = $_SESSION['cart'];
            $ids = array_map('intval', array_keys($cart));
            sort($ids);

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Khóa các dòng sản phẩm và đọc lại giá, tồn kho từ database.
            $stmt = $conn->prepare("
                SELECT ProductID, ProductName, Price, StockQuantity
                FROM products
                WHERE IsActive = 1 AND ProductID IN ($placeholders)
                ORDER BY ProductID
                FOR UPDATE
            ");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (count($rows) !== count($ids)) {
                throw new RuntimeException('Một số sản phẩm trong giỏ không còn được bán.');
            }

            $total = 0.0;
            $lines = [];

            foreach ($rows as $row) {
                $qty = (int) $cart[$row['ProductID']];

                if ($qty <= 0 || $qty > (int) $row['StockQuantity']) {
                    throw new RuntimeException(
                        'Sản phẩm "' . $row['ProductName'] . '" không đủ hàng.'
                    );
                }

                $price = (float) $row['Price'];
                $total += $price * $qty;
                $lines[] = [
                    'ProductID' => (int) $row['ProductID'],
                    'Quantity'  => $qty,
                    'UnitPrice' => $price,
                ];
            }

            // Khách hàng: dùng tài khoản đang đăng nhập hoặc tạo bản ghi mới cho khách vãng lai.
            if ($account) {
                $customerID = (int) $account['CustomerID'];

                $stmt = $conn->prepare("
                    UPDATE customers SET Phone = ?, Address = ?, City = ?
                    WHERE CustomerID = ?
                ");
                $stmt->bind_param('sssi', $form['phone'], $form['address'], $form['city'], $customerID);
                $stmt->execute();
                $stmt->close();
            } else {
                $email = $form['email'] !== '' ? $form['email'] : null;

                $stmt = $conn->prepare("
                    INSERT INTO customers (CustomerName, ContactName, Phone, Address, City, Email)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'ssssss',
                    $form['customer_name'], $form['customer_name'],
                    $form['phone'], $form['address'], $form['city'], $email
                );
                $stmt->execute();
                $customerID = $conn->insert_id;
                $stmt->close();
            }

            $stmt = $conn->prepare("
                INSERT INTO orders (CustomerID, TotalAmount, Status)
                VALUES (?, ?, 'Pending')
            ");
            $stmt->bind_param('id', $customerID, $total);
            $stmt->execute();
            $orderID = $conn->insert_id;
            $stmt->close();

            $detail = $conn->prepare("
                INSERT INTO orderdetail (OrderID, ProductID, Quantity, UnitPrice)
                VALUES (?, ?, ?, ?)
            ");
            $stock = $conn->prepare("
                UPDATE products SET StockQuantity = StockQuantity - ?
                WHERE ProductID = ?
            ");

            foreach ($lines as $line) {
                $detail->bind_param(
                    'iiid',
                    $orderID, $line['ProductID'], $line['Quantity'], $line['UnitPrice']
                );
                $detail->execute();

                $stock->bind_param('ii', $line['Quantity'], $line['ProductID']);
                $stock->execute();
            }

            $detail->close();
            $stock->close();

            $conn->commit();

            // Chỉ xóa giỏ hàng sau khi đã commit thành công.
            $_SESSION['cart'] = [];

            header('Location: /order-success.php?id=' . $orderID);
            exit;

        } catch (RuntimeException $e) {
            $conn->rollback();
            $error = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = 'Không thể tạo đơn hàng. Email có thể đã được dùng cho một tài khoản khác.';
        }
    }
}

/* Hiển thị tóm tắt giỏ hàng */
$ids = array_map('intval', array_keys($_SESSION['cart']));
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $conn->prepare("
    SELECT ProductID, ProductName, Price
    FROM products
    WHERE IsActive = 1 AND ProductID IN ($placeholders)
    ORDER BY ProductID
");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$summaryTotal = 0.0;
foreach ($summary as $i => $row) {
    $qty = (int) $_SESSION['cart'][$row['ProductID']];
    $summary[$i]['Quantity'] = $qty;
    $summary[$i]['LineTotal'] = (float) $row['Price'] * $qty;
    $summaryTotal += $summary[$i]['LineTotal'];
}

$pageTitle = 'Đặt hàng';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

$readonly = $account ? 'readonly' : '';

?>

<main class="container py-4">

    <h1 class="mb-4">Đặt hàng</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-md-7">
            <form method="post">

                <?php if (!$account): ?>
                    <p class="text-muted">
                        Bạn đang đặt hàng với tư cách khách.
                        <a href="/login.php?redirect=/checkout.php">Đăng nhập</a> để dùng tài khoản của bạn.
                    </p>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="customerName" class="form-label">Họ tên</label>
                    <input type="text" class="form-control" id="customerName" name="customer_name"
                           value="<?= htmlspecialchars($form['customer_name']) ?>" <?= $readonly ?> required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars($form['email']) ?>" <?= $readonly ?>>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="<?= htmlspecialchars($form['phone']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Địa chỉ giao hàng</label>
                    <input type="text" class="form-control" id="address" name="address"
                           value="<?= htmlspecialchars($form['address']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="city" class="form-label">Thành phố</label>
                    <input type="text" class="form-control" id="city" name="city"
                           value="<?= htmlspecialchars($form['city']) ?>">
                </div>

                <button type="submit" class="btn btn-success">Xác nhận đặt hàng</button>
                <a href="/cart.php" class="btn btn-outline-secondary">Quay lại giỏ hàng</a>

            </form>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Đơn hàng của bạn</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($summary as $row): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= htmlspecialchars($row['ProductName']) ?> &times; <?= (int) $row['Quantity'] ?></span>
                            <span><?= number_format($row['LineTotal'], 0, ',', '.') ?> đ</span>
                        </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between fw-bold">
                        <span>Tổng cộng</span>
                        <span><?= number_format($summaryTotal, 0, ',', '.') ?> đ</span>
                    </li>
                </ul>
            </div>
        </div>

    </div>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
