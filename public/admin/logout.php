<?php

require_once '/var/www/src/config/session.php';

unset($_SESSION['admin_authenticated'], $_SESSION['admin_username']);
session_regenerate_id(true);

header('Location: /admin/login.php');
exit;
