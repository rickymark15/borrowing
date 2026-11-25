<?php require_once 'includes/layout.php';
$u = require_login('my-requests.php');
if ($u['role'] === 'admin') redirect('admin/index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c = db()->prepare("DELETE FROM requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    $c->execute([(int)($_POST['id'] ?? 0), $u['id']]);
    flash($c->rowCount() ? 'Request cancelled.' : 'That request can no longer be cancelled.', $c->rowCount() ? 'ok' : 'error');
    redirect('my-requests.php');
}
$st = db()->prepare('SELECT r.*, COALESCE(i.name, "Equipment") AS name, COALESCE(i.image, "assets/img/default.svg") AS image 
    FROM requests r 
    LEFT JOIN equipment i ON i.id = COALESCE(r.item_id, r.equipment_id) 
    WHERE r.user_id = ? 
    ORDER BY r.id DESC');
$st->execute([$u['id']]); $rows = $st->fetchAll();
page_header('My requests', 'mine'); ?>
<div class="head-row"><h1>My requests</h1><a class="btn" href="browse.php">Browse equipment</a></div>
<?php if (!$rows): ?><p>You haven't requested anything yet.</p><?php else: ?>
<div class="scroll"><table>
  <caption class="sr">Your borrow requests</caption>
  <thead><tr><th scope="col">Item</th><th scope="col">Needed by</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col"><span class="sr">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <th scope="row"><span class="thumb-row"><img class="thumb" src="<?= e(item_img($r)) ?>" alt=""><?= e($r['name']) ?></span></th>
      <td><?= e($r['needed_by']) ?></td><td><?= e(ucfirst($r['fulfillment'])) ?></td>
      <td><span class="badge s-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td><?php if ($r['status'] === 'pending'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small ghost" type="submit">Cancel<span class="sr"> request for <?= e($r['name']) ?></span></button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; page_footer(); ?>
