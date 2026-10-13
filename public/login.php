<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

// Chỉ cho phép chuyển hướng về đường dẫn nội bộ.
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/';
if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
    $redirect = '/';
}

if (isset($_SESSION['customer_id'])) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("
        SELECT CustomerID, CustomerName, PasswordHash
        FROM customers
        WHERE Email = ?
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        $customer
        && $customer['PasswordHash'] !== null
        && password_verify($password, $customer['PasswordHash'])
    ) {
        session_regenerate_id(true);

        $_SESSION['customer_id'] = (int) $customer['CustomerID'];
        $_SESSION['customer_name'] = $customer['CustomerName'];

        header('Location: ' . $redirect);
        exit;
    }

    // Một thông báo chung, không tiết lộ email có tồn tại hay không.
    $error = 'Email hoặc mật khẩu không đúng.';
}

$pageTitle = 'Đăng nhập';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<main class="container py-4" style="max-width: 460px;">

    <h1 class="mb-4">Đăng nhập</h1>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Tạo tài khoản thành công. Hãy đăng nhập.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Mật khẩu</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Đăng nhập</button>
        <a href="/register.php" class="btn btn-link">Tạo tài khoản</a>
    </form>

</main>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
