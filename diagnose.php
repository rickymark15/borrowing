<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

$pdo = db();
echo 'PHP ' . PHP_VERSION . "\n";
echo 'MySQL ' . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
echo 'Database: ' . DB_NAME . "\n\n";
echo "Accounts (passwords unhashed in database):\n";
foreach ($pdo->query('SELECT id, username, email, role, COALESCE(NULLIF(password,""), password_hash) AS pwd FROM users ORDER BY id') as $row) {
    echo '#' . $row['id'] . ' ' . $row['username'] . ' ' . $row['email'] . ' role=' . $row['role'] . ' password=' . $row['pwd'] . "\n";
}
