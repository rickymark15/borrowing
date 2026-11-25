<?php
require_once __DIR__ . '/includes/auth.php';
if (!user() || user()['role'] !== 'admin') {
    flash('Admin access only. Please log in with an admin account.', 'error');
    redirect('login.php');
}
redirect('admin/index.php');
