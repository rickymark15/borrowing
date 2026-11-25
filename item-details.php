<?php require_once 'includes/layout.php';
$st = db()->prepare('SELECT * FROM equipment WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]);
$it = $st->fetch(); $u = user();
if (!$it) http_response_code(404);
page_header($it['name'] ?? 'Item not found', 'browse'); ?>
<p class="crumb"><a href="browse.php">Back to all equipment</a></p>
<?php if (!$it): ?><h1>Item not found</h1><p><a href="browse.php">Browse equipment</a></p>
<?php else: ?>
<div class="detail">
  <img class="detail-img" src="<?= e(item_img($it)) ?>" alt="<?= e($it['name']) ?>">
  <div>
    <h1><?= e($it['name']) ?></h1>
    <p><?= e($it['summary']) ?></p>
    <p><?= stock_badge($it) ?></p>
    <dl>
      <dt>Condition</dt><dd><?= e($it['item_condition']) ?></dd>
      <dt>Weight limit</dt><dd><?= e($it['weight_limit']) ?></dd>
      <dt>Pickup location</dt><dd><?= e($it['pickup_location']) ?></dd>
    </dl>
    <?php if (!empty($it['usage_notes'])): ?>
      <h2>Usage notes</h2><p><?= e($it['usage_notes']) ?></p>
    <?php endif; ?>
    <?php if ($u && $u['role'] === 'admin'): ?>
      <a class="btn" href="admin/equipment-edit.php?id=<?= (int)$it['id'] ?>">Edit this equipment</a>
    <?php elseif (($it['status'] ?? '') === 'maintenance'): ?>
      <p><strong>This equipment is currently undergoing maintenance.</strong> Check back soon.</p>
    <?php elseif ((int)$it['quantity_available'] === 0): ?>
      <p><strong>Everything is borrowed right now.</strong> Check back soon.</p>
    <?php elseif ($u): ?>
      <a class="btn big" href="request.php?item=<?= (int)$it['id'] ?>">Request this item</a>
    <?php else: ?>
      <a class="btn big" href="login.php?next=<?= urlencode('request.php?item=' . (int)$it['id']) ?>">Log in to request</a>
      <p class="muted">New here? <a href="register.php?next=<?= urlencode('request.php?item=' . (int)$it['id']) ?>">Create an account</a>.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; page_footer(); ?>
