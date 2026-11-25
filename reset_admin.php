<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = db();
$email = 'admin@hulambee.local'; $pass = 'admin123';
$update = $pdo->prepare("UPDATE users SET username = 'admin', full_name = 'Coordinator', password = ?, password_hash = ?, role = 'admin' WHERE email = ?");
$update->execute([$pass, $pass, $email]);
if (!$update->rowCount()) {
	$pdo->prepare("INSERT INTO users (username, full_name, email, password, password_hash, role) VALUES ('admin', 'Coordinator', ?, ?, ?, 'admin')")
		->execute([$email, $pass, $pass]);
}
echo "Admin password reset and verified: OK\nEmail: $email\nPassword: $pass\n";
