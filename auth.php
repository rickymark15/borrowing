<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $s = db()->prepare('SELECT id, username, full_name, email, role FROM users WHERE id = ?');
            $s->execute([$_SESSION['uid']]);
            $u = $s->fetch() ?: null;
        }
    }
    return $u;
}

function require_login(string $next = ''): array {
    $u = user();
    if (!$u) {
        flash('Log in or create an account to request equipment.', 'info');
        redirect('login.php' . ($next ? '?next=' . urlencode($next) : ''));
    }
    return $u;
}

function require_admin(): array {
    $u = user();
    if (!$u || $u['role'] !== 'admin') {
        flash('Admin access only. Please log in with an admin account.', 'error');
        redirect('../login.php');
    }
    return $u;
}

function safe_next(?string $n): string {
    return ($n && preg_match('/^[a-z0-9\-]+\.php(\?[\w=&%\-]*)?$/i', $n)) ? $n : '';
}

function flash(string $msg, string $type = 'ok'): void {
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}
