<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

fazer_logout();
header('Location: ' . SITE_URL . '/login.php');
exit;
