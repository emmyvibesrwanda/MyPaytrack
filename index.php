<?php
require_once __DIR__ . '/includes/auth.php';

if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
} else {
    header('Location: auth.php');
}
exit();
?>