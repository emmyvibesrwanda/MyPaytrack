<?php
require_once __DIR__ . '/includes/auth.php';
$auth->logout();
header('Location: auth.php');
exit();
?>