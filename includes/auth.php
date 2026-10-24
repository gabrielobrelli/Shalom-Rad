<?php
/**
 * Autenticação da administração.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function admin_logado(): bool
{
    return !empty($_SESSION['admin_id']);
}

function exigir_login_admin(): void
{
    if (!admin_logado()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function tentar_login(string $username, string $senha): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($senha, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }

    return false;
}

function fazer_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
