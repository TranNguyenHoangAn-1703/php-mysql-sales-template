<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

$error = '';
$form = ['customer_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form['customer_name'] = trim($_POST['customer_name'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($form['customer_name'] === '' || $form['email'] === '' || $password === '') {
        $error = 'Vui lòng nhập họ tên, email và mật khẩu.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu cần ít nhất 6 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO customers (CustomerName, ContactName, Phone, Email, PasswordHash)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'sssss',
            $form['customer_name'], $form['customer_name'],
            $form['phone'], $form['email'], $hash
        );

        try {
            $stmt->execute();
            $stmt->close();

            header('Location: /login.php?registered=1');
            exit;

        } catch (mysqli_sql_exception $e) {
            // Mã lỗi 1062 là trùng giá trị UNIQUE (email đã tồn tại).
            $error = $e->getCode() === 1062
                ? 'Email này đã được đăng ký.'
                : 'Không thể tạo tài khoản.';
        }
    }
}

$pageTitle = 'Đăng ký';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-4" style="max-width: 520px;">

    <h1 class="mb-4">Đăng ký</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="customerName" class="form-label">Họ tên</label>
            <input type="text" class="form-control" id="customerName" name="customer_name"
                   value="<?= htmlspecialchars($form['customer_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= htmlspecialchars($form['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">Số điện thoại</label>
            <input type="text" class="form-control" id="phone" name="phone"
                   value="<?= htmlspecialchars($form['phone']) ?>">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Mật khẩu</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
            <label for="passwordConfirm" class="form-label">Nhập lại mật khẩu</label>
            <input type="password" class="form-control" id="passwordConfirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn btn-primary">Tạo tài khoản</button>
        <a href="/login.php" class="btn btn-link">Đã có tài khoản?</a>
    </form>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
