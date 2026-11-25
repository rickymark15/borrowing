<?php require_once '../includes/layout.php';
require_admin();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
    $pdo->beginTransaction();
    $r = $pdo->prepare('SELECT * FROM requests WHERE id = ? FOR UPDATE'); $r->execute([$id]); $req = $r->fetch();
    $eqId = $req ? ($req['item_id'] ?: $req['equipment_id']) : 0;
    if ($req && $act === 'lend' && $req['status'] === 'pending') {
        $s = $pdo->prepare('SELECT quantity_available FROM equipment WHERE id = ? FOR UPDATE'); $s->execute([$eqId]);
        if ((int)$s->fetchColumn() > 0) {
            $pdo->prepare("UPDATE equipment SET status = IF(quantity_available - 1 > 0, 'available', 'borrowed'), quantity_available = quantity_available - 1 WHERE id = ?")->execute([$eqId]);
            $pdo->prepare("UPDATE requests SET status='borrowed', borrowed_at=CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            flash('Approved. The item is now marked as borrowed.');
        } else flash('Cannot approve: none of this item are available.', 'error');
    } elseif ($req && $act === 'decline' && $req['status'] === 'pending') {
        $pdo->prepare("UPDATE requests SET status='declined' WHERE id = ?")->execute([$id]);
        flash('Request declined and removed from the dashboard.');
    } elseif ($req && $act === 'return' && $req['status'] === 'borrowed') {
        $pdo->prepare("UPDATE equipment SET quantity_available = LEAST(quantity_available + 1, quantity_total), status = 'available' WHERE id = ?")->execute([$eqId]);
        $pdo->prepare("UPDATE requests SET status='returned', returned_at=CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
        flash('Marked as returned. Stock updated and the loan removed from the dashboard.');
    }
    $pdo->commit();
    redirect('index.php');
}
$pending = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn();
$borrowed = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status='borrowed'")->fetchColumn();
$types = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$out = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE quantity_available = 0')->fetchColumn();
$rows = $pdo->query("SELECT r.*, COALESCE(i.name, 'Equipment') AS name, COALESCE(i.image, 'assets/img/default.svg') AS image, COALESCE(u.full_name, r.requester_name, 'Member') AS full_name, COALESCE(u.email, '') AS email
    FROM requests r 
    LEFT JOIN equipment i ON i.id = COALESCE(r.item_id, r.equipment_id) 
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.status IN ('pending','borrowed')
    ORDER BY r.status = 'pending' DESC, r.needed_by")->fetchAll();
admin_header('Dashboard', 'dash'); ?>
<div class="head-row"><h1>Dashboard</h1><a class="btn" href="equipment-edit.php">Add equipment</a></div>
<div class="kpis">
  <div class="kpi"><strong><?= $pending ?></strong><span>Pending requests</span></div>
  <div class="kpi"><strong><?= $borrowed ?></strong><span>Currently borrowed</span></div>
  <div class="kpi"><strong><?= $types ?></strong><span>Equipment types</span></div>
  <div class="kpi"><strong><?= $out ?></strong><span>Out of stock</span></div>
</div>
<h2>Pending and borrowed</h2>
<?php if (!$rows): ?><p class="panel">Nothing to track right now. New requests will show up here.</p><?php else: ?>
<div class="scroll"><table>
  <caption class="sr">Pending requests and borrowed equipment</caption>
  <thead><tr><th scope="col">Equipment</th><th scope="col">Member</th><th scope="col">Needed by</th><th scope="col">Method</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <th scope="row"><span class="thumb-row"><img class="thumb" src="../<?= e(item_img($r)) ?>" alt=""><?= e($r['name']) ?></span></th>
      <td><?= e($r['full_name']) ?><br><small><?= e($r['email']) ?><?= $r['phone'] ? ' | ' . e($r['phone']) : '' ?></small></td>
      <td><?= e($r['needed_by']) ?></td>
      <td><?= e(ucfirst($r['fulfillment'])) ?></td>
      <td><span class="badge s-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
      <td class="actions">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php if ($r['status'] === 'pending'): ?>
          <button class="btn small" name="action" value="lend" type="submit">Approve and lend<span class="sr"> <?= e($r['name']) ?> to <?= e($r['full_name']) ?></span></button>
          <button class="btn small ghost" name="action" value="decline" type="submit">Decline<span class="sr"> <?= e($r['name']) ?> for <?= e($r['full_name']) ?></span></button>
        <?php else: ?>
          <button class="btn small" name="action" value="return" type="submit">Mark returned<span class="sr"> <?= e($r['name']) ?> from <?= e($r['full_name']) ?></span></button>
        <?php endif; ?></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; admin_footer(); ?>
