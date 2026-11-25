<?php require_once 'includes/layout.php'; page_header('Browse equipment', 'browse');
$q = trim($_GET['q'] ?? '');
$st = db()->prepare('SELECT * FROM equipment WHERE (name LIKE ? OR summary LIKE ? OR description LIKE ? OR category LIKE ?) ORDER BY (quantity_available > 0) DESC, name');
$st->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
$items = $st->fetchAll(); ?>
<div class="head-row"><h1>Browse equipment</h1>
  <form class="search" method="get" role="search">
    <label class="sr" for="q">Search equipment</label>
    <input id="q" name="q" type="search" placeholder="Search tools" value="<?= e($q) ?>">
    <button class="btn" type="submit">Search</button>
  </form></div>
<?php if (!$items): ?><p>No equipment matches "<?= e($q) ?>". <a href="browse.php">Show all</a>.</p><?php endif; ?>
<div class="grid"><?php foreach ($items as $it) item_card($it); ?></div>
<?php page_footer(); ?>
