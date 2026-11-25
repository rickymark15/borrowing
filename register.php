
<?php
require_once 'includes/layout.php';

if (user()) {
    redirect('browse.php');
}

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$err = [];
$v = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $v = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? ''))
    ];

    $pw = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($v['name'] === '') {
        $err['name'] = 'Enter your full name.';
    }

    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Enter a valid email address.';
    }

    if (strlen($pw) < 8) {
        $err['password'] = 'Use at least 8 characters.';
    }

    if ($pw !== $confirm) {
        $err['confirm'] = 'Passwords do not match.';
    }

    if (!$err) {
        try {
            $ex = db()->prepare(
                'SELECT id FROM users WHERE email = ?'
            );
            $ex->execute([$v['email']]);

            if ($ex->fetch()) {
                $err['email'] =
                    'That email already has an account. Log in instead.';
            }
        } catch (PDOException $e) {
            error_log('HulamBee signup check failed: ' . $e->getMessage());
            $err['general'] =
                'Unable to check your account. Please check the database connection.';
        }
    }

    if (!$err) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO users (username, full_name, email, password, password_hash, role)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                'member_' . bin2hex(random_bytes(5)),
                $v['name'],
                $v['email'],
                $pw,
                $pw,
                'user'
            ]);

            $newId = (int) db()->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['uid'] = $newId;

            flash('Account created. Welcome to HulamBee!');
            redirect($next ?: 'browse.php');

        } catch (PDOException $e) {
            error_log('HulamBee signup failed: ' . $e->getMessage());

            if ((string) $e->getCode() === '23000') {
                $err['email'] =
                    'That email already has an account. Please log in.';
            } else {
                $err['general'] =
                    'Account creation failed. Check your database connection and users table.';
            }
        }
    }
}

$f = fn($k) => isset($err[$k])
    ? '<span class="err">' . e($err[$k]) . '</span>'
    : '';

page_header('Create account', 'login');
?>

<div class="auth">
    <h1>Create your account</h1>

    <?php if (isset($err['general'])): ?>
        <p class="err"><?= e($err['general']) ?></p>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>

        <input type="hidden" name="next" value="<?= e($next) ?>">

        <label for="name">Full name</label>
        <input
            id="name"
            name="name"
            type="text"
            required
            autocomplete="name"
            value="<?= e($v['name']) ?>"
        >
        <?= $f('name') ?>

        <label for="email">Email</label>
        <input
            id="email"
            name="email"
            type="email"
            required
            autocomplete="email"
            value="<?= e($v['email']) ?>"
        >
        <?= $f('email') ?>

        <label for="password">Password (8+ characters)</label>
        <input
            id="password"
            name="password"
            type="password"
            required
            autocomplete="new-password"
        >
        <?= $f('password') ?>

        <label for="confirm">Confirm password</label>
        <input
            id="confirm"
            name="confirm"
            type="password"
            required
            autocomplete="new-password"
        >
        <?= $f('confirm') ?>

        <button class="btn big full" type="submit">
            Create account
        </button>
    </form>

    <p class="muted">
        Already registered?
        <a href="login.php<?= $next ? '?next=' . urlencode($next) : '' ?>">
            Log in
        </a>
    </p>
</div>

<?php page_footer(); ?>
