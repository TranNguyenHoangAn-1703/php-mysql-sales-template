<?php

require_once '/var/www/src/config/session.php';

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    $redirect = $_SERVER['REQUEST_URI'] ?? '/admin/';
    if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
        $redirect = '/admin/';
    }

    header('Location: /admin/login.php?redirect=' . rawurlencode($redirect));
    exit;
}
