<?php

require_once '/var/www/src/config/session.php';

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/admin/';
if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//') || !str_starts_with($redirect, '/admin/')) {
    $redirect = '/admin/';
}

if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUsername = $_POST['username'] ?? '';
    $submittedPassword = $_POST['password'] ?? '';
    $username = is_string($submittedUsername) ? trim($submittedUsername) : '';
    $password = is_string($submittedPassword) ? $submittedPassword : '';

    if (hash_equals('admin', $username) && hash_equals('123456', $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = 'admin';

        header('Location: ' . $redirect);
        exit;
    }

    $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
}

$pageTitle = 'Đăng nhập quản trị';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-4" style="max-width: 460px;">
    <h1 class="mb-4">Đăng nhập quản trị</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <div class="mb-3">
            <label for="username" class="form-label">Tên đăng nhập</label>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?= htmlspecialchars($username) ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Mật khẩu</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Đăng nhập</button>
        <a href="/" class="btn btn-link">Về trang bán hàng</a>
    </form>
</main>

<?php require_once '/var/www/src/includes/frontend/footer.php'; ?>
