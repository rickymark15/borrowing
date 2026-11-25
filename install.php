<?php
/**
 * HulamBee - Database Installer & System Diagnostics Center
 * Supports both CLI command-line execution and interactive Web UI.
 */

// If executed from command line (CLI), run straightforward installer and output plain text
if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/config.php';
    try {
        $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = db();
        
        echo "========================================================\n";
        echo " HulamBee MySQL Database Setup & Verification\n";
        echo "========================================================\n";
        echo "Status:    READY\n";
        echo "Database:  " . DB_NAME . "\n";
        echo "Host:      " . DB_HOST . "\n";
        echo "PHP:       " . PHP_VERSION . "\n";
        echo "--------------------------------------------------------\n";
        echo "Users:     " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . "\n";
        echo "Equipment: " . $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn() . "\n";
        echo "Requests:  " . $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn() . "\n";
        echo "--------------------------------------------------------\n";
        echo "Default Admin:  admin@hulambee.local / admin123\n";
        echo "Default Member: member@example.com   / Member123!\n";
        echo "========================================================\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Installation Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// -------------------------------------------------------------
// Interactive Web Installer & Diagnostics Interface
// -------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Helper for escaping HTML
if (!function_exists('h')) {
    function h(?string $s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$actionNotice = null;
$actionError = null;
$dbError = null;
$pdo = null;

// Connect to MySQL server and verify database
try {
    $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = db();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// Handle interactive setup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'sync_schema') {
            setup_database($pdo);
            $actionNotice = 'Database schema verified, tables synchronized, and default views updated.';
        } elseif ($action === 'reset_admin') {
            $email = 'admin@hulambee.local';
            $pass = 'admin123';
            $stmt = $pdo->prepare("UPDATE users SET username = 'admin', full_name = 'Coordinator', password = ?, password_hash = ?, role = 'admin' WHERE email = ?");
            $stmt->execute([$pass, $pass, $email]);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare("INSERT INTO users (username, full_name, email, password, password_hash, role) VALUES ('admin', 'Coordinator', ?, ?, ?, 'admin')")
                    ->execute([$email, $pass, $pass]);
            }
            $actionNotice = 'Default admin credentials restored successfully (admin@hulambee.local / admin123).';
        } elseif ($action === 'seed_items') {
            $insert = $pdo->prepare('INSERT INTO equipment (name, description, summary, category, item_condition, weight_limit, pickup_location, usage_notes, image, quantity_total, quantity_available) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $samples = [
                ['Extension Ladder', 'Aluminum 6 m ladder for roofs, gutters and painting.', 'Aluminum 6 m ladder for roofs, gutters and painting.', 'Tools', 'Good', '150 kg', 'Barangay hall storage room', 'Set on level ground and have a second person hold the base. Wipe clean before returning.', 'assets/img/ladder.svg', 3, 2],
                ['Power Drill', 'Corded drill with a basic set of bits.', 'Corded drill with a basic set of bits.', 'Tools', 'Excellent', 'Not applicable', 'Community center desk', 'Wear eye protection. Return with all bits in the case and the cord wrapped.', 'assets/img/drill.svg', 5, 5],
                ['Wheelbarrow', 'Steel single-wheel barrow for soil, sand and garden waste.', 'Steel single-wheel barrow for soil, sand and garden waste.', 'Garden', 'Fair', '90 kg', 'Community garden shed', 'Check tire pressure before use. Rinse off mud before returning.', 'assets/img/wheelbarrow.svg', 4, 4],
                ['Pressure Washer', 'Electric washer for patios, fences and vehicles.', 'Electric washer for patios, fences and vehicles.', 'Tools', 'Good', 'Not applicable', 'Barangay hall storage room', 'Connect to a water source before switching on. Drain the hose after use.', 'assets/img/pressure-washer.svg', 2, 2],
            ];
            $added = 0;
            foreach ($samples as $item) {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM equipment WHERE name = ?');
                $chk->execute([$item[0]]);
                if ((int)$chk->fetchColumn() === 0) {
                    $insert->execute($item);
                    $added++;
                }
            }
            $actionNotice = $added > 0 
                ? "Added {$added} default equipment items to the catalog." 
                : 'All standard demo equipment items are already present in the catalog.';
        }
    } catch (Throwable $e) {
        $actionError = 'Action failed: ' . $e->getMessage();
    }
}

// Gather System and Database Metrics
$stats = [
    'users_total' => 0,
    'users_admin' => 0,
    'users_member' => 0,
    'items_total' => 0,
    'items_available' => 0,
    'items_total_units' => 0,
    'requests_total' => 0,
    'requests_pending' => 0,
    'requests_borrowed' => 0,
    'requests_returned' => 0,
    'schema_version' => 'Unknown',
    'mysql_version' => 'Unknown',
];

$tablesList = [];

if ($pdo) {
    try {
        $stats['mysql_version'] = $pdo->query('SELECT VERSION()')->fetchColumn() ?: 'Unknown';
        $stats['users_total'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stats['users_admin'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $stats['users_member'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();

        $stats['items_total'] = (int)$pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
        $stats['items_available'] = (int)$pdo->query("SELECT COALESCE(SUM(quantity_available), 0) FROM equipment WHERE status = 'available'")->fetchColumn();
        $stats['items_total_units'] = (int)$pdo->query("SELECT COALESCE(SUM(quantity_total), 0) FROM equipment")->fetchColumn();

        $stats['requests_total'] = (int)$pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();
        $stats['requests_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn();
        $stats['requests_borrowed'] = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'borrowed'")->fetchColumn();
        $stats['requests_returned'] = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'returned'")->fetchColumn();

        $verStmt = $pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'");
        $stats['schema_version'] = $verStmt ? ($verStmt->fetchColumn() ?: '1') : '1';

        // Fetch detailed table rows
        $tblQuery = $pdo->query("SHOW TABLE STATUS");
        if ($tblQuery) {
            while ($row = $tblQuery->fetch(PDO::FETCH_ASSOC)) {
                $tablesList[] = [
                    'name' => $row['Name'],
                    'rows' => (int)($row['Rows'] ?? 0),
                    'engine' => $row['Engine'] ?? 'InnoDB',
                    'collation' => $row['Collation'] ?? 'utf8mb4_unicode_ci',
                ];
            }
        }
    } catch (Throwable $e) {
        // Fallback for missing tables
    }
}

// System Requirements Checks
$uploadsDir = __DIR__ . '/uploads';
$uploadsWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0777, true);
    $uploadsWritable = is_dir($uploadsDir) && is_writable($uploadsDir);
}

$checks = [
    [
        'label' => 'PHP Version',
        'value' => 'PHP ' . PHP_VERSION,
        'ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'desc' => 'PHP 7.4 or higher is required (PHP 8+ recommended).'
    ],
    [
        'label' => 'PDO MySQL Driver',
        'value' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing',
        'ok' => extension_loaded('pdo_mysql'),
        'desc' => 'Required for connecting to MariaDB / MySQL.'
    ],
    [
        'label' => 'MySQL Connection',
        'value' => $pdo ? DB_HOST . ' (' . DB_NAME . ')' : 'Failed',
        'ok' => (bool)$pdo,
        'desc' => $pdo ? 'Connected to database `' . DB_NAME . '`' : 'Ensure MySQL is running in XAMPP Control Panel.'
    ],
    [
        'label' => 'Uploads Directory',
        'value' => $uploadsWritable ? 'Writable' : 'Read-Only / Missing',
        'ok' => $uploadsWritable,
        'desc' => 'Storage folder for equipment images and user attachments.'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Setup & System Health | HulamBee</title>
  <link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* --------------------------------------------------
       HulamBee Installer Enhanced Animations & Styling
       Matches HulamBee Honeycomb & Forest Sage palette
    ----------------------------------------------------- */

    /* Staggered entrance animations */
    @keyframes setupFadeUp {
      from {
        opacity: 0;
        transform: translateY(14px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes pulseRing {
      0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(21, 105, 60, 0.45);
      }
      70% {
        transform: scale(1);
        box-shadow: 0 0 0 7px rgba(21, 105, 60, 0);
      }
      100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(21, 105, 60, 0);
      }
    }

    @keyframes pulseDotYellow {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(0.85); }
    }

    .install-wrap {
      max-width: 68rem;
      margin: 0 auto;
      padding: 1.5rem 1rem 3rem;
      animation: setupFadeUp 0.35s ease-out;
    }

    /* Top Brand & Status Banner */
    .install-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--r);
      padding: 1.25rem 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .install-header:hover {
      border-color: #cbd5e1;
      box-shadow: var(--shadow-md);
    }

    .install-brand {
      display: flex;
      align-items: center;
      gap: 0.9rem;
    }

    .install-brand img {
      width: 44px;
      height: 44px;
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .install-brand:hover img {
      transform: rotate(8deg) scale(1.08);
    }

    .install-brand h1 {
      font-size: 1.45rem;
      margin: 0;
      color: var(--ink);
      letter-spacing: -0.02em;
    }

    .install-brand p {
      margin: 0.15rem 0 0;
      font-size: 0.88rem;
      color: var(--muted);
    }

    /* Status Pill with live pulse */
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.4rem 0.9rem;
      border-radius: 9999px;
      font-size: 0.86rem;
      font-weight: 600;
      line-height: 1;
      border: 1px solid transparent;
      transition: transform 0.2s ease;
    }

    .status-pill:hover {
      transform: scale(1.02);
    }

    .status-pill.ready {
      background: var(--ok-bg);
      color: var(--ok);
      border-color: #bbf0d0;
    }

    .status-pill.error {
      background: var(--bad-bg);
      color: var(--bad);
      border-color: #f7c5c2;
    }

    .pulse-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--ok);
      animation: pulseRing 1.8s infinite;
      display: inline-block;
    }

    .pulse-dot.error {
      background: var(--bad);
      animation: none;
    }

    /* Hero Setup Section */
    .install-hero {
      background: linear-gradient(135deg, var(--slate) 0%, #172127 100%);
      color: #ffffff;
      border-radius: var(--r);
      padding: 2.2rem;
      margin-bottom: 1.5rem;
      position: relative;
      overflow: hidden;
      border-left: 5px solid var(--yellow);
      box-shadow: var(--shadow-md);
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .install-hero:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(25, 33, 38, 0.16);
    }

    .install-hero h2 {
      color: #ffffff;
      font-size: clamp(1.4rem, 3vw, 1.85rem);
      margin: 0 0 0.5rem;
      letter-spacing: -0.01em;
    }

    .install-hero p {
      color: #cbd5e1;
      max-width: 44rem;
      margin: 0 0 1.25rem;
      font-size: 1rem;
      line-height: 1.6;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.75rem;
    }

    /* Hover elevation on Interactive Cards */
    .grid-4 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .setup-card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--r);
      padding: 1.15rem 1.25rem;
      position: relative;
      transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .setup-card:hover {
      transform: translateY(-4px);
      border-color: #cbd5e1;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.07);
    }

    .setup-card.feature-card:hover {
      border-top: 3px solid var(--yellow);
    }

    .card-header-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }

    .card-header-row .card-tag {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 700;
      color: var(--muted);
    }

    .check-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      font-size: 0.85rem;
      font-weight: bold;
      transition: transform 0.2s ease;
    }

    .setup-card:hover .check-icon {
      transform: scale(1.15);
    }

    .check-icon.ok {
      background: var(--ok-bg);
      color: var(--ok);
    }

    .check-icon.warn {
      background: var(--warn-bg);
      color: var(--warn);
    }

    .check-icon.bad {
      background: var(--bad-bg);
      color: var(--bad);
    }

    .card-val {
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 0.25rem;
      line-height: 1.2;
    }

    .card-sub {
      font-size: 0.84rem;
      color: var(--muted);
      margin: 0;
      line-height: 1.4;
    }

    /* KPI Numbers Grid */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .kpi-box {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--r);
      padding: 1.2rem 1.3rem;
      transition: all 0.2s ease;
      cursor: default;
    }

    .kpi-box:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
      border-color: var(--slate);
    }

    .kpi-box strong {
      display: block;
      font-size: 1.85rem;
      font-weight: 800;
      color: var(--slate);
      line-height: 1.1;
      margin-bottom: 0.35rem;
    }

    .kpi-box span.kpi-title {
      font-weight: 600;
      font-size: 0.95rem;
      color: var(--ink);
      display: block;
      margin-bottom: 0.15rem;
    }

    .kpi-box span.kpi-meta {
      font-size: 0.82rem;
      color: var(--muted);
      display: block;
    }

    /* Credentials & Quick Launch Panel */
    .creds-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }

    .cred-card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--r);
      padding: 1.35rem;
      transition: all 0.2s ease;
      position: relative;
    }

    .cred-card:hover {
      transform: translateY(-3px);
      border-color: var(--yellow);
      box-shadow: 0 10px 22px rgba(0,0,0,0.07);
    }

    .cred-head {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .cred-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 8px;
      background: var(--paper);
      border: 1px solid var(--line);
      font-size: 1.1rem;
    }

    .cred-info h3 {
      margin: 0;
      font-size: 1.05rem;
      color: var(--ink);
    }

    .cred-info p {
      margin: 0;
      font-size: 0.82rem;
      color: var(--muted);
    }

    .copy-field {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--paper);
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 0.45rem 0.75rem;
      margin-bottom: 0.5rem;
      font-size: 0.88rem;
      transition: border-color 0.15s ease, background 0.15s ease;
    }

    .copy-field:hover {
      border-color: #cbd5e1;
      background: #eef2f0;
    }

    .copy-field code {
      font-family: Consolas, "Courier New", monospace;
      font-weight: 600;
      color: var(--ink);
    }

    .btn-copy {
      background: transparent;
      border: 1px solid #cbd5e1;
      color: var(--slate);
      padding: 0.2rem 0.55rem;
      border-radius: 4px;
      font-size: 0.78rem;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.15s ease;
      position: relative;
    }

    .btn-copy:hover {
      background: var(--yellow);
      border-color: var(--yellow);
      color: var(--ink);
      transform: translateY(-1px);
    }

    .btn-copy:active {
      transform: scale(0.95);
    }

    /* Flash Notice Alerts */
    .alert-banner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 0.9rem 1.25rem;
      border-radius: var(--r);
      margin-bottom: 1.25rem;
      font-size: 0.95rem;
      font-weight: 500;
      animation: setupFadeUp 0.25s ease-out;
    }

    .alert-banner.ok {
      background: var(--ok-bg);
      color: var(--ok);
      border: 1px solid #bbf0d0;
    }

    .alert-banner.err {
      background: var(--bad-bg);
      color: var(--bad);
      border: 1px solid #f7c5c2;
    }

    /* Database Table Section */
    .tables-section {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--r);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
    }

    .tables-section h3 {
      margin: 0 0 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .table-responsive {
      overflow-x: auto;
    }

    .db-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.92rem;
    }

    .db-table th {
      text-align: left;
      padding: 0.65rem 0.75rem;
      background: var(--paper);
      color: var(--muted);
      font-weight: 600;
      border-bottom: 2px solid var(--line);
      white-space: nowrap;
    }

    .db-table td {
      padding: 0.75rem;
      border-bottom: 1px solid var(--line);
      color: var(--ink);
    }

    .db-table tr:hover td {
      background: #fafcfb;
    }

    .badge-status {
      display: inline-block;
      padding: 0.2rem 0.55rem;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      background: var(--ok-bg);
      color: var(--ok);
    }

    /* Action Toolbar */
    .toolbar-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-top: 1.25rem;
    }

    /* Tooltip Toast */
    .copy-toast {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      background: var(--slate);
      color: #ffffff;
      padding: 0.65rem 1.2rem;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 600;
      box-shadow: 0 6px 18px rgba(0,0,0,0.18);
      z-index: 9999;
      transform: translateY(20px);
      opacity: 0;
      pointer-events: none;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border-left: 4px solid var(--yellow);
    }

    .copy-toast.show {
      transform: translateY(0);
      opacity: 1;
    }

    .btn.submitting {
      opacity: 0.7;
      pointer-events: none;
      cursor: wait;
    }
  </style>
</head>
<body>

<div class="install-wrap">

  <!-- Top Navigation & Header -->
  <header class="install-header">
    <div class="install-brand">
      <a href="index.php" title="Back to HulamBee">
        <img src="assets/img/logo.svg" alt="HulamBee Logo">
      </a>
      <div>
        <h1>HulamBee Setup & Diagnostic Hub</h1>
        <p>Database management, environment inspection, and maintenance utilities</p>
      </div>
    </div>

    <div>
      <?php if ($pdo): ?>
        <div class="status-pill ready">
          <span class="pulse-dot"></span>
          <span>Database Ready & Synchronized</span>
        </div>
      <?php else: ?>
        <div class="status-pill error">
          <span class="pulse-dot error"></span>
          <span>Database Connection Issue</span>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <!-- Flash Alerts -->
  <?php if ($actionNotice): ?>
    <div class="alert-banner ok" role="alert">
      <span><strong>Success:</strong> <?= h($actionNotice) ?></span>
      <button type="button" class="btn-copy" onclick="this.parentElement.remove()">Dismiss</button>
    </div>
  <?php endif; ?>

  <?php if ($actionError): ?>
    <div class="alert-banner err" role="alert">
      <span><strong>Error:</strong> <?= h($actionError) ?></span>
      <button type="button" class="btn-copy" onclick="this.parentElement.remove()">Dismiss</button>
    </div>
  <?php endif; ?>

  <?php if ($dbError): ?>
    <div class="alert-banner err" role="alert">
      <div>
        <strong>Database Connection Failed:</strong> <?= h($dbError) ?><br>
        <small style="color: var(--bad); display: block; margin-top: 0.25rem;">
          Make sure Apache and MySQL are running in your <strong>XAMPP Control Panel</strong>.
        </small>
      </div>
      <form method="post" style="margin: 0;">
        <input type="hidden" name="action" value="sync_schema">
        <button type="submit" class="btn small">Retry Connection</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Main Hero Card -->
  <section class="install-hero">
    <h2><?= $pdo ? 'HulamBee Database System is Online' : 'HulamBee Installer' ?></h2>
    <p>
      <?= $pdo 
        ? 'The MySQL database <strong>' . h(DB_NAME) . '</strong> is active on <strong>' . h(DB_HOST) . '</strong>. All schemas, foreign keys, catalog views, and user tables are verified and prepared for equipment sharing.'
        : 'Could not connect to the MySQL server. Check your database credentials in <code>config.php</code> and confirm MySQL is started in XAMPP.'
      ?>
    </p>

    <div class="hero-actions">
      <?php if ($pdo): ?>
        <a class="btn" href="index.php">
          <span>Go to HulamBee Home</span>
          <span style="margin-left: 0.4rem;">&rarr;</span>
        </a>
        <a class="btn ghost-light" href="browse.php">Browse Catalog</a>
        <a class="btn ghost-light" href="admin/index.php">Admin Dashboard</a>
        <a class="btn ghost-light" href="diagnose.php">CLI Diagnostics</a>
      <?php else: ?>
        <form method="post" style="display: inline;">
          <input type="hidden" name="action" value="sync_schema">
          <button type="submit" class="btn">Initialize Database</button>
        </form>
      <?php endif; ?>
    </div>
  </section>

  <!-- Pre-Flight Environment Checklist -->
  <section class="grid-4" aria-label="Environment Checklist">
    <?php foreach ($checks as $chk): ?>
      <div class="setup-card feature-card">
        <div class="card-header-row">
          <span class="card-tag"><?= h($chk['label']) ?></span>
          <span class="check-icon <?= $chk['ok'] ? 'ok' : 'bad' ?>">
            <?= $chk['ok'] ? '&#10003;' : '&#10007;' ?>
          </span>
        </div>
        <div class="card-val"><?= h($chk['value']) ?></div>
        <p class="card-sub"><?= h($chk['desc']) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- Live Statistics Overview -->
  <?php if ($pdo): ?>
    <section class="kpi-grid" aria-label="System Metrics">
      <div class="kpi-box">
        <strong><?= $stats['users_total'] ?></strong>
        <span class="kpi-title">Registered Accounts</span>
        <span class="kpi-meta"><?= $stats['users_admin'] ?> Admin &bull; <?= $stats['users_member'] ?> Members</span>
      </div>

      <div class="kpi-box">
        <strong><?= $stats['items_total'] ?></strong>
        <span class="kpi-title">Equipment Catalog</span>
        <span class="kpi-meta"><?= $stats['items_available'] ?> of <?= $stats['items_total_units'] ?> units available</span>
      </div>

      <div class="kpi-box">
        <strong><?= $stats['requests_total'] ?></strong>
        <span class="kpi-title">Borrow Requests</span>
        <span class="kpi-meta"><?= $stats['requests_pending'] ?> pending &bull; <?= $stats['requests_borrowed'] ?> borrowed</span>
      </div>

      <div class="kpi-box">
        <strong>v<?= h($stats['schema_version']) ?></strong>
        <span class="kpi-title">Schema Version</span>
        <span class="kpi-meta">MySQL <?= h(explode('-', $stats['mysql_version'])[0]) ?></span>
      </div>
    </section>
  <?php endif; ?>

  <!-- Default Accounts & Quick Access Credentials -->
  <section class="creds-container" aria-label="Default Credentials">
    <!-- Admin Credentials Card -->
    <div class="cred-card">
      <div class="cred-head">
        <div class="cred-badge" title="Administrator">🛠️</div>
        <div class="cred-info">
          <h3>Administrator Account</h3>
          <p>Full control over equipment catalog, approval queue, and returns</p>
        </div>
      </div>

      <div class="copy-field">
        <span>Email: <code>admin@hulambee.local</code></span>
        <button type="button" class="btn-copy" onclick="copyText('admin@hulambee.local', this)">Copy</button>
      </div>

      <div class="copy-field">
        <span>Password: <code>admin123</code></span>
        <button type="button" class="btn-copy" onclick="copyText('admin123', this)">Copy</button>
      </div>

      <div class="toolbar-actions" style="margin-top: 1rem;">
        <a class="btn small" href="login.php">Log In as Admin</a>
        <form method="post" style="display: inline;" onsubmit="return confirm('Reset the default admin account to admin@hulambee.local / admin123?');">
          <input type="hidden" name="action" value="reset_admin">
          <button type="submit" class="btn small ghost">Restore Admin</button>
        </form>
      </div>
    </div>

    <!-- Member Credentials Card -->
    <div class="cred-card">
      <div class="cred-head">
        <div class="cred-badge" title="Community Member">👤</div>
        <div class="cred-info">
          <h3>Test Member Account</h3>
          <p>Standard community account to submit borrow requests and review loans</p>
        </div>
      </div>

      <div class="copy-field">
        <span>Email: <code>member@example.com</code></span>
        <button type="button" class="btn-copy" onclick="copyText('member@example.com', this)">Copy</button>
      </div>

      <div class="copy-field">
        <span>Password: <code>Member123!</code></span>
        <button type="button" class="btn-copy" onclick="copyText('Member123!', this)">Copy</button>
      </div>

      <div class="toolbar-actions" style="margin-top: 1rem;">
        <a class="btn small" href="login.php">Log In as Member</a>
        <a class="btn small ghost" href="register.php">Create New Account</a>
      </div>
    </div>
  </section>

  <!-- Database Tables & Maintenance Utilities -->
  <?php if ($pdo): ?>
    <section class="tables-section">
      <h3>
        <span>Database Tables & Health</span>
        <span style="font-size: 0.85rem; font-weight: normal; color: var(--muted);">
          Charset: <code>utf8mb4_unicode_ci</code>
        </span>
      </h3>

      <div class="table-responsive">
        <table class="db-table">
          <thead>
            <tr>
              <th>Table Name</th>
              <th>Type / Engine</th>
              <th>Row Count</th>
              <th>Collation</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($tablesList)): ?>
              <?php foreach ($tablesList as $tbl): ?>
                <tr>
                  <td><strong><?= h($tbl['name']) ?></strong></td>
                  <td><?= h($tbl['engine'] ?: 'VIEW') ?></td>
                  <td><?= number_format($tbl['rows']) ?> rows</td>
                  <td><code><?= h($tbl['collation']) ?></code></td>
                  <td><span class="badge-status">Active</span></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--muted); padding: 1.5rem;">
                  No tables found. Click "Run Schema Sync" below to create them.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Action Maintenance Tools -->
      <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--line);">
        <strong style="display: block; margin-bottom: 0.75rem; font-size: 0.95rem;">Maintenance Tools:</strong>
        <div class="toolbar-actions">
          <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="sync_schema">
            <button type="submit" class="btn small">
              <span>&#x21bb;</span> Re-run Schema Sync
            </button>
          </form>

          <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="seed_items">
            <button type="submit" class="btn small ghost">
              <span>&#43;</span> Seed Default Equipment
            </button>
          </form>

          <form method="post" style="display: inline;" onsubmit="return confirm('Restore the admin account?');">
            <input type="hidden" name="action" value="reset_admin">
            <button type="submit" class="btn small ghost">
              <span>&#128273;</span> Fix / Reset Admin User
            </button>
          </form>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- Quick Links Footer -->
  <footer style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; color: var(--muted); font-size: 0.88rem;">
    <div>
      <strong>HulamBee</strong> &bull; CIT 210 Equipment Sharing Platform
    </div>
    <div style="display: flex; gap: 1rem;">
      <a href="index.php" style="color: var(--slate); text-decoration: none; font-weight: 500;">Home</a>
      <a href="browse.php" style="color: var(--slate); text-decoration: none; font-weight: 500;">Browse</a>
      <a href="about.php" style="color: var(--slate); text-decoration: none; font-weight: 500;">About</a>
      <a href="login.php" style="color: var(--slate); text-decoration: none; font-weight: 500;">Sign In</a>
    </div>
  </footer>

</div>

<!-- Animated Copy Toast Notification -->
<div id="copyToast" class="copy-toast" role="status" aria-live="polite">
  <span>&#10003;</span>
  <span id="toastMsg">Copied to clipboard!</span>
</div>

<script>
/**
 * Interactive Copy to Clipboard with animation and toast feedback
 */
function copyText(text, btnElement) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(showSuccess).catch(fallback);
  } else {
    fallback();
  }

  function showSuccess() {
    var toast = document.getElementById('copyToast');
    var toastMsg = document.getElementById('toastMsg');
    if (toast && toastMsg) {
      toastMsg.textContent = 'Copied "' + text + '"';
      toast.classList.add('show');
      setTimeout(function() {
        toast.classList.remove('show');
      }, 2200);
    }
    if (btnElement) {
      var orig = btnElement.textContent;
      btnElement.textContent = 'Copied!';
      btnElement.style.background = 'var(--yellow)';
      btnElement.style.borderColor = 'var(--yellow)';
      setTimeout(function() {
        btnElement.textContent = orig;
        btnElement.style.background = '';
        btnElement.style.borderColor = '';
      }, 1500);
    }
  }

  function fallback() {
    var input = document.createElement('textarea');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    try {
      document.execCommand('copy');
      showSuccess();
    } catch (e) {
      alert('Copied: ' + text);
    }
    document.body.removeChild(input);
  }
}

// Add smooth loading transition on form submission
document.addEventListener('DOMContentLoaded', function() {
  var forms = document.querySelectorAll('form');
  forms.forEach(function(form) {
    form.addEventListener('submit', function() {
      var submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.classList.add('submitting');
        submitBtn.innerHTML = 'Processing...';
      }
    });
  });
});
</script>

</body>
</html>
