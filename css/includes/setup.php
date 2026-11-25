<?php
/**
 * Database schema synchronization, migration, and seed helpers.
 */

function default_equipment_items(): array {
    return [
        [
            'Extension Ladder',
            'Aluminum 6 m ladder for roofs, gutters and painting.',
            'Aluminum 6 m ladder for roofs, gutters and painting.',
            'Tools',
            'Good',
            '150 kg',
            'Barangay hall storage room',
            'Set on level ground and have a second person hold the base. Wipe clean before returning.',
            'assets/img/extension-ladder.webp',
            3,
            2,
        ],
        [
            'Power Drill',
            'Corded drill with a basic set of bits.',
            'Corded drill with a basic set of bits.',
            'Tools',
            'Excellent',
            'Not applicable',
            'Community center desk',
            'Wear eye protection. Return with all bits in the case and the cord wrapped.',
            'assets/img/power-drill.jpg',
            5,
            5,
        ],
        [
            'Wheelbarrow',
            'Steel single-wheel barrow for soil, sand and garden waste.',
            'Steel single-wheel barrow for soil, sand and garden waste.',
            'Garden',
            'Fair',
            '90 kg',
            'Community garden shed',
            'Check tire pressure before use. Rinse off mud before returning.',
            'assets/img/wheelbarrow.png',
            4,
            4,
        ],
        [
            'Pressure Washer',
            'Electric washer for patios, fences and vehicles.',
            'Electric washer for patios, fences and vehicles.',
            'Tools',
            'Good',
            'Not applicable',
            'Barangay hall storage room',
            'Connect to a water source before switching on. Drain the hose after use.',
            'assets/img/pressure-washer.jpg',
            2,
            2,
        ],
    ];
}

function seed_default_equipment(PDO $pdo): int {
    $insert = $pdo->prepare(
        'INSERT INTO equipment (name, description, summary, category, item_condition, weight_limit, pickup_location, usage_notes, image, quantity_total, quantity_available)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $check = $pdo->prepare('SELECT COUNT(*) FROM equipment WHERE name = ?');
    $added = 0;

    foreach (default_equipment_items() as $item) {
        $check->execute([$item[0]]);
        if ((int)$check->fetchColumn() === 0) {
            $insert->execute($item);
            $added++;
        }
    }

    return $added;
}

function reset_default_admin(PDO $pdo): void {
    $email = 'admin@hulambee.local';
    $pass = 'admin123';
    $update = $pdo->prepare(
        "UPDATE users
         SET username = 'admin', full_name = 'Coordinator', password = ?, password_hash = ?, role = 'admin'
         WHERE email = ?"
    );
    $update->execute([$pass, $pass, $email]);

    if ($update->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if (!$exists->fetch()) {
            $pdo->prepare(
                "INSERT INTO users (username, full_name, email, password, password_hash, role)
                 VALUES ('admin', 'Coordinator', ?, ?, ?, 'admin')"
            )->execute([$email, $pass, $pass]);
        }
    }
}

function setup_database(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL DEFAULT '',
        role ENUM('user','member','admin') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS equipment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        summary VARCHAR(255) NOT NULL DEFAULT '',
        category VARCHAR(50) DEFAULT 'General',
        item_condition VARCHAR(50) NOT NULL DEFAULT 'Good',
        weight_limit VARCHAR(50) NOT NULL DEFAULT 'Not applicable',
        pickup_location VARCHAR(150) NOT NULL DEFAULT '',
        usage_notes TEXT NULL,
        status ENUM('available','borrowed','maintenance') NOT NULL DEFAULT 'available',
        image VARCHAR(255) NOT NULL DEFAULT 'assets/img/default.svg',
        quantity_total INT UNSIGNED NOT NULL DEFAULT 1,
        quantity_available INT UNSIGNED NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        equipment_id INT NOT NULL,
        item_id INT NULL,
        requester_name VARCHAR(100) NULL,
        phone VARCHAR(30) NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        needed_by DATE NOT NULL,
        fulfillment ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
        notes TEXT NULL,
        status ENUM('pending','approved','borrowed','declined','rejected','returned') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        borrowed_at DATETIME NULL,
        returned_at DATETIME NULL,
        CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_requests_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_meta (
        meta_key VARCHAR(64) PRIMARY KEY,
        meta_value VARCHAR(64) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $version = $pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'")->fetchColumn();
    if ($version === '3') {
        return;
    }

    $ensureColumn = static function (string $table, string $column, string $definition) use ($pdo): void {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);
        if (!(int)$statement->fetchColumn()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    };

    $ensureColumn('users', 'username', "VARCHAR(50) NOT NULL DEFAULT ''");
    $ensureColumn('users', 'full_name', "VARCHAR(100) NOT NULL DEFAULT ''");
    $ensureColumn('users', 'password', "VARCHAR(255) NOT NULL DEFAULT ''");
    $ensureColumn('users', 'password_hash', "VARCHAR(255) NOT NULL DEFAULT ''");
    $ensureColumn('equipment', 'summary', "VARCHAR(255) NOT NULL DEFAULT ''");
    $ensureColumn('equipment', 'item_condition', "VARCHAR(50) NOT NULL DEFAULT 'Good'");
    $ensureColumn('equipment', 'weight_limit', "VARCHAR(50) NOT NULL DEFAULT 'Not applicable'");
    $ensureColumn('equipment', 'pickup_location', "VARCHAR(150) NOT NULL DEFAULT ''");
    $ensureColumn('equipment', 'usage_notes', 'TEXT NULL');
    $ensureColumn('equipment', 'quantity_total', 'INT UNSIGNED NOT NULL DEFAULT 1');
    $ensureColumn('equipment', 'quantity_available', 'INT UNSIGNED NOT NULL DEFAULT 1');
    $ensureColumn('requests', 'item_id', 'INT NULL');
    $ensureColumn('requests', 'requester_name', 'VARCHAR(100) NULL');
    $ensureColumn('requests', 'phone', 'VARCHAR(30) NULL');
    $ensureColumn('requests', 'start_date', 'DATE NULL');
    $ensureColumn('requests', 'end_date', 'DATE NULL');
    $ensureColumn('requests', 'needed_by', 'DATE NULL');
    $ensureColumn('requests', 'fulfillment', "ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup'");
    $ensureColumn('requests', 'notes', 'TEXT NULL');
    $ensureColumn('requests', 'borrowed_at', 'DATETIME NULL');
    $ensureColumn('requests', 'returned_at', 'DATETIME NULL');

    $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','member','admin') NOT NULL DEFAULT 'user'");
    $pdo->exec("ALTER TABLE requests MODIFY status ENUM('pending','approved','borrowed','declined','rejected','returned') NOT NULL DEFAULT 'pending'");

    $pdo->exec("UPDATE users SET full_name = username WHERE full_name = '' OR full_name IS NULL");

    // Keep seeded/existing accounts in plain text so passwords can be inspected in phpMyAdmin
    $pdo->exec("UPDATE users SET email = 'admin@hulambee.local' WHERE email = 'admin@shareshed.local'");
    $pdo->exec("UPDATE users SET password = 'admin123', password_hash = 'admin123' WHERE email = 'admin@hulambee.local' AND (password = '' OR password LIKE '$2y$%' OR password_hash LIKE '$2y$%')");
    $pdo->exec("UPDATE users SET password = 'Member123!', password_hash = 'Member123!' WHERE email = 'member@example.com' AND (password = '' OR password LIKE '$2y$%' OR password_hash LIKE '$2y$%')");
    $pdo->exec("UPDATE users SET password = password_hash WHERE (password = '' OR password IS NULL) AND password_hash != ''");
    $pdo->exec("UPDATE users SET password_hash = password WHERE (password_hash = '' OR password_hash IS NULL) AND password != ''");

    $pdo->exec("UPDATE equipment SET summary = COALESCE(NULLIF(summary, ''), description, '')");
    $pdo->exec("UPDATE equipment SET quantity_total = 1 WHERE quantity_total = 0");
    $pdo->exec("UPDATE equipment SET quantity_available = CASE WHEN status = 'available' THEN quantity_total ELSE 0 END WHERE quantity_available = 1 AND status <> 'available'");
    $pdo->exec("UPDATE equipment SET status = CASE WHEN quantity_available > 0 THEN 'available' ELSE 'borrowed' END WHERE status <> 'maintenance'");

    // Migrate legacy placeholder SVGs and old upload filenames to assets/img/
    $imageMigrations = [
        'assets/img/extension-ladder.webp' => ['assets/img/ladder.svg', 'uploads/extention ladder.webp'],
        'assets/img/power-drill.jpg'       => ['assets/img/drill.svg', 'uploads/powerdrill.jpg'],
        'assets/img/wheelbarrow.png'       => ['assets/img/wheelbarrow.svg', 'uploads/wheelborrow.png'],
        'assets/img/pressure-washer.jpg'   => ['assets/img/pressure-washer.svg', 'uploads/preassure washer .jpg', 'uploads/preassure washer.jpg'],
    ];
    $migrateImg = $pdo->prepare('UPDATE equipment SET image = ? WHERE image = ?');
    foreach ($imageMigrations as $newPath => $oldPaths) {
        foreach ($oldPaths as $oldPath) {
            $migrateImg->execute([$newPath, $oldPath]);
        }
    }

    $pdo->exec("UPDATE requests SET item_id = equipment_id WHERE item_id IS NULL");
    $pdo->exec("UPDATE requests SET requester_name = (SELECT full_name FROM users WHERE users.id = requests.user_id) WHERE requester_name IS NULL OR requester_name = ''");
    $pdo->exec("UPDATE requests SET needed_by = COALESCE(needed_by, end_date, start_date, CURRENT_DATE())");
    $pdo->exec("UPDATE requests SET start_date = COALESCE(start_date, needed_by), end_date = COALESCE(end_date, needed_by)");
    $pdo->exec("UPDATE requests SET borrowed_at = COALESCE(borrowed_at, created_at) WHERE status = 'approved'");
    $pdo->exec("UPDATE requests SET status = 'borrowed' WHERE status = 'approved'");
    $pdo->exec("UPDATE requests SET status = 'declined' WHERE status = 'rejected'");

    $pdo->exec("ALTER TABLE requests MODIFY needed_by DATE NOT NULL");
    $pdo->exec("ALTER TABLE requests MODIFY start_date DATE NOT NULL, MODIFY end_date DATE NOT NULL");

    $pdo->exec("CREATE OR REPLACE VIEW items AS
        SELECT id, name, description, summary, category, item_condition, weight_limit,
               pickup_location, usage_notes, status, image, quantity_total, quantity_available, created_at
        FROM equipment");

    $admin = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $admin->execute(['admin@hulambee.local']);
    if (!$admin->fetch()) {
        reset_default_admin($pdo);
    }

    if ((int)$pdo->query('SELECT COUNT(*) FROM equipment')->fetchColumn() === 0) {
        seed_default_equipment($pdo);
    }

    $member = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $member->execute(['member@example.com']);
    if (!$member->fetch()) {
        $pdo->prepare(
            "INSERT INTO users (username, full_name, email, password, password_hash, role)
             VALUES (?, ?, ?, ?, ?, 'user')"
        )->execute(['member', 'Ana Reyes', 'member@example.com', 'Member123!', 'Member123!']);
    }

    $pdo->exec(
        "INSERT INTO app_meta (meta_key, meta_value) VALUES ('schema_version', '3')
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
    );
}
