<?php
require_once __DIR__ . '/auth.php';

function flashes_html(): void {
    foreach (take_flashes() as [$type, $msg]) {
        echo '<div class="flash ' . e($type) . '" role="status">' . e($msg) . '</div>';
    }
}

/**
 * Resolve an equipment image path safely for either root pages or subdirectories.
 */
function item_img(?array $it, string $prefix = ''): string {
    $fallback = $prefix . 'assets/img/default.svg';
    if (empty($it) || empty($it['image'])) {
        return $fallback;
    }

    $img = str_replace('\\', '/', trim($it['image']));
    if (preg_match('#^https?://#i', $img)) {
        return $img;
    }

    $img = ltrim($img, '/');
    if (str_starts_with($img, 'assets/') || str_starts_with($img, 'uploads/')) {
        $resolved = $img;
    } elseif (is_file(__DIR__ . '/../assets/img/' . $img)) {
        $resolved = 'assets/img/' . $img;
    } elseif (is_file(__DIR__ . '/../uploads/' . $img)) {
        $resolved = 'uploads/' . $img;
    } else {
        return $fallback;
    }

    if (!is_file(__DIR__ . '/../' . $resolved)) {
        return $fallback;
    }

    $encoded = implode('/', array_map('rawurlencode', explode('/', $resolved)));
    return $prefix . $encoded;
}

function stock_badge(array $it): string {
    if (($it['status'] ?? '') === 'maintenance') {
        return '<span class="badge out">Under maintenance</span>';
    }
    $a = (int)($it['quantity_available'] ?? 0);
    $t = (int)($it['quantity_total'] ?? $a);
    $cls = $a === 0 ? 'out' : ($a <= 1 ? 'low' : 'in');
    $txt = $a === 0 ? 'All borrowed' : "$a of $t available";
    return '<span class="badge ' . $cls . '">' . $txt . '</span>';
}

function item_card(array $it): void { ?>
  <article class="card">
    <a class="card-img" href="item-details.php?id=<?= (int)$it['id'] ?>" tabindex="-1" aria-hidden="true">
      <img src="<?= e(item_img($it)) ?>" alt="" loading="lazy">
    </a>
    <div class="card-body">
      <h3><a href="item-details.php?id=<?= (int)$it['id'] ?>"><?= e($it['name']) ?></a></h3>
      <p><?= e($it['summary']) ?></p>
      <div class="card-foot"><?= stock_badge($it) ?>
        <a class="btn small" href="item-details.php?id=<?= (int)$it['id'] ?>">View<span class="sr"> <?= e($it['name']) ?></span></a>
      </div>
    </div>
  </article>
<?php }

function render_head(string $title, string $prefix = '', string $bodyClass = ''): void { ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<link rel="icon" href="<?= e($prefix) ?>assets/img/logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($prefix) ?>css/style.css">
<script src="<?= e($prefix) ?>assets/js/app.js" defer></script>
</head><body<?= $bodyClass ? ' class="' . e($bodyClass) . '"' : '' ?>>
<div class="scroll-progress" aria-hidden="true"></div>
<a class="skip" href="#main">Skip to content</a>
<?php }

function render_nav_toggle_script(string $toggleSel, string $targetSel, ?string $extraSel = null): void { ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var toggle = document.querySelector(<?= json_encode($toggleSel) ?>);
  var target = document.querySelector(<?= json_encode($targetSel) ?>);
  var extra = <?= $extraSel ? 'document.querySelector(' . json_encode($extraSel) . ')' : 'null' ?>;
  if (toggle && target) {
    toggle.addEventListener('click', function() {
      var open = target.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (extra) extra.classList.toggle('is-open', open);
    });
  }
});
</script>
</body></html>
<?php }

function page_header(string $title, string $active = ''): void {
    $u = user();
    $link = fn(string $f, string $l, string $k) => '<li><a href="' . $f . '"' . ($active === $k ? ' aria-current="page"' : '') . '>' . $l . '</a></li>';
    render_head($title . ' | HulamBee');
    ?>
<header class="topbar"><div class="wrap bar">
  <div class="bar-brand">
    <a class="brand" href="index.php"><img src="assets/img/logo.svg" alt="" width="34" height="34"><span>HulamBee</span></a>
    <button type="button" class="nav-toggle" aria-expanded="false" aria-label="Toggle navigation">
      <span class="bar-icon"></span>
    </button>
  </div>
  <nav class="nav-menu" aria-label="Main">
    <ul class="nav-list">
      <?= $link('index.php', 'Home', 'home') ?>
      <?= $link('browse.php', 'Browse', 'browse') ?>
      <?= $link('about.php', 'About', 'about') ?>
      <?php if ($u && in_array($u['role'], ['user', 'member'], true)) echo $link('my-requests.php', 'My requests', 'mine'); ?>
      <?php if ($u && $u['role'] === 'admin') echo $link('admin/index.php', 'Admin dashboard', 'admin'); ?>
    </ul>
    <div class="acct">
      <?php if ($u): ?>
        <span class="hello">Hi, <?= e(explode(' ', $u['full_name'])[0]) ?></span>
        <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn small ghost" type="submit">Log out</button></form>
      <?php else: ?>
        <a class="login-link" href="login.php"<?= $active === 'login' ? ' aria-current="page"' : '' ?>>Log in</a>
        <a class="btn small" href="register.php">Sign up</a>
      <?php endif; ?>
    </div>
  </nav>
</div></header>
<main id="main"><div class="wrap">
<?php flashes_html();
}

function page_footer(): void {
    $u = user(); ?>
</div></main>
<footer class="foot"><div class="wrap foot-grid">
  <div><strong>HulamBee</strong><p>Borrow tools from your neighbors instead of buying new.</p></div>
  <div><strong>Explore</strong><ul><li><a href="browse.php">Browse equipment</a></li><li><a href="about.php">About and help</a></li></ul></div>
  <div><strong>Account</strong><ul>
    <?php if ($u && $u['role'] === 'admin'): ?>
      <li><a href="admin/index.php">Admin dashboard</a></li>
      <li><a href="admin/equipment.php">Manage equipment</a></li>
    <?php elseif ($u): ?>
      <li><a href="my-requests.php">My requests</a></li>
      <li><a href="request.php">Request an item</a></li>
    <?php else: ?>
      <li><a href="login.php">Log in</a></li>
      <li><a href="register.php">Create account</a></li>
    <?php endif; ?>
  </ul></div>
</div><p class="copy">CIT 210 project</p></footer>
<?php
    render_nav_toggle_script('.nav-toggle', '.nav-menu');
}

function admin_header(string $title, string $active): void {
    $u = require_admin();
    $link = fn(string $f, string $l, string $k) => '<li><a href="' . $f . '"' . ($active === $k ? ' aria-current="page"' : '') . '>' . $l . '</a></li>';
    render_head($title . ' | HulamBee Admin', '../', 'adm');
    ?>
<div class="shell">
<aside class="side">
  <div class="side-brand-row">
    <a class="brand" href="index.php"><img src="../assets/img/logo.svg" alt="" width="32" height="32"><span>HulamBee <small>Admin</small></span></a>
    <button type="button" class="side-toggle" aria-expanded="false" aria-label="Toggle admin menu">
      <span class="bar-icon"></span>
    </button>
  </div>
  <nav class="side-nav" aria-label="Admin"><ul>
    <?= $link('index.php', 'Dashboard', 'dash') ?>
    <?= $link('equipment.php', 'Equipment', 'equip') ?>
    <?= $link('equipment-edit.php', 'Add equipment', 'add') ?>
    <li><a href="../index.php">View member site</a></li>
  </ul></nav>
  <div class="side-foot">
    <span><?= e($u['full_name']) ?></span>
    <form method="post" action="../logout.php"><?= csrf_field() ?><button class="btn small ghost" type="submit">Log out</button></form>
  </div>
</aside>
<main id="main" class="adm-main">
<?php flashes_html();
}

function admin_footer(): void { ?>
</main></div>
<?php
    render_nav_toggle_script('.side-toggle', '.side-nav', '.side-foot');
}
