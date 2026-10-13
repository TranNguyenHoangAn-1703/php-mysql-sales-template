<?php

require_once '/var/www/src/config/session.php';

// Chỉ bỏ thông tin đăng nhập, giữ lại giỏ hàng.
unset($_SESSION['customer_id'], $_SESSION['customer_name']);

session_regenerate_id(true);

header('Location: /');
exit;
