<?php require_once 'includes/layout.php';
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
if ($u = user()) redirect($u['role'] === 'admin' ? 'admin/index.php' : 'browse.php');
$error = ''; $loginInput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $loginInput = trim($_POST['login'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Look up by email or username
    $st = db()->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1');
    $st->execute([$loginInput, $loginInput]);
    $row = $st->fetch();

    $valid = false;
    if ($row) {
        $storedPass = $row['password'] ?? '';
        $storedHash = $row['password_hash'] ?? '';

        // Check plain text unhashed password first
        if ($password !== '' && ($password === $storedPass || $password === $storedHash)) {
            $valid = true;
        } elseif ($storedHash !== '' && str_starts_with($storedHash, '$2y$') && password_verify($password, $storedHash)) {
            // Un-hash legacy password so user can view it in MySQL
            $valid = true;
            $up = db()->prepare('UPDATE users SET password = ?, password_hash = ? WHERE id = ?');
            $up->execute([$password, $password, $row['id']]);
        }
    }

    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        redirect($row['role'] === 'admin' ? 'admin/index.php' : ($next ?: 'browse.php'));
    }
    $error = 'Email/username or password is incorrect.';
}
page_header('Log in', 'login'); ?>
<div class="auth">
  <h1>Log in</h1>
  <?php if ($error): ?><div class="flash error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <label for="login">Email or Username</label>
    <input id="login" name="login" type="text" required autocomplete="username" value="<?= e($loginInput) ?>" placeholder="e.g. admin or admin@hulambee.local">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <button class="btn big full" type="submit">Log in</button>
  </form>
  <p class="muted">No account yet? <a href="register.php<?= $next ? '?next=' . urlencode($next) : '' ?>">Create one</a></p>
</div>
<?php page_footer(); ?>
