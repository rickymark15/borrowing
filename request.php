<?php require_once 'includes/layout.php';
$u = require_login('request.php' . (isset($_GET['item']) ? '?item=' . (int)$_GET['item'] : ''));
if ($u['role'] === 'admin') { flash('Admins manage requests from the dashboard.', 'info'); redirect('admin/index.php'); }
$items = db()->query("SELECT id, name, quantity_available FROM equipment WHERE status != 'maintenance' AND quantity_available > 0 ORDER BY name")->fetchAll();
$ids = array_column($items, 'id');
$err = [];
$v = ['item' => (int)($_GET['item'] ?? 0), 'phone' => '', 'needed_by' => '', 'fulfillment' => '', 'notes' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v = ['item' => (int)($_POST['item'] ?? 0), 'phone' => trim($_POST['phone'] ?? ''), 'needed_by' => trim($_POST['needed_by'] ?? ''),
          'fulfillment' => $_POST['fulfillment'] ?? '', 'notes' => trim($_POST['notes'] ?? '')];
    if (!in_array($v['item'], $ids)) $err['item'] = 'Choose an item that is in stock.';
    $d = DateTime::createFromFormat('Y-m-d', $v['needed_by']);
    if (!$d || $d->format('Y-m-d') !== $v['needed_by'] || $v['needed_by'] < date('Y-m-d')) $err['needed_by'] = 'Choose a date today or later.';
    if (!in_array($v['fulfillment'], ['pickup', 'delivery'])) $err['fulfillment'] = 'Choose pickup or delivery.';
    if (empty($_POST['agree'])) $err['agree'] = 'Agree to the guidelines to continue.';
    if (!$err) {
        $dup = db()->prepare("SELECT 1 FROM requests WHERE user_id=? AND (item_id=? OR equipment_id=?) AND status IN ('pending','borrowed')");
        $dup->execute([$u['id'], $v['item'], $v['item']]);
        if ($dup->fetch()) $err['item'] = 'You already have an open request or loan for this item.';
    }
    if (!$err) {
        db()->prepare('INSERT INTO requests (user_id,equipment_id,item_id,requester_name,phone,start_date,end_date,needed_by,fulfillment,notes) VALUES (?,?,?,?,?,?,?,?,?,?)')
          ->execute([$u['id'], $v['item'], $v['item'], $u['full_name'], $v['phone'] ?: null, $v['needed_by'], $v['needed_by'], $v['needed_by'], $v['fulfillment'], $v['notes'] ?: null]);
        flash('Request sent. A coordinator will review it soon.');
        redirect('my-requests.php');
    }
}
$f = fn($k) => isset($err[$k]) ? '<span class="err">' . e($err[$k]) . '</span>' : '';
page_header('Request an item', 'request'); ?>
<div class="auth wide">
  <h1>Request an item</h1>
  <p class="muted">Requesting as <strong><?= e($u['full_name']) ?></strong> (<?= e($u['email']) ?>)</p>
  <?php if (!$items): ?><p>Nothing is in stock right now. <a href="browse.php">Browse equipment</a>.</p><?php else: ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label for="item">Item</label>
    <select id="item" name="item" required>
      <option value="">Choose an item</option>
      <?php foreach ($items as $it): ?>
        <option value="<?= (int)$it['id'] ?>"<?= $v['item'] === (int)$it['id'] ? ' selected' : '' ?>><?= e($it['name']) ?> (<?= (int)$it['quantity_available'] ?> available)</option>
      <?php endforeach; ?>
    </select><?= $f('item') ?>
    <div class="cols">
      <div><label for="needed_by">Needed by</label>
        <input id="needed_by" name="needed_by" type="date" min="<?= date('Y-m-d') ?>" required value="<?= e($v['needed_by']) ?>"><?= $f('needed_by') ?></div>
      <div><label for="phone">Phone (optional)</label>
        <input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e($v['phone']) ?>"></div>
    </div>
    <fieldset><legend>Pickup or delivery</legend>
      <label class="inline"><input type="radio" name="fulfillment" value="pickup" required<?= $v['fulfillment'] === 'pickup' ? ' checked' : '' ?>> Pickup</label>
      <label class="inline"><input type="radio" name="fulfillment" value="delivery"<?= $v['fulfillment'] === 'delivery' ? ' checked' : '' ?>> Delivery</label>
      <?= $f('fulfillment') ?></fieldset>
    <label for="notes">Notes (optional)</label><textarea id="notes" name="notes" rows="3"><?= e($v['notes']) ?></textarea>
    <label class="inline"><input type="checkbox" name="agree" value="1" required> I agree to the <a href="about.php">borrowing guidelines</a></label><?= $f('agree') ?>
    <button class="btn big" type="submit">Send request</button>
  </form>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
