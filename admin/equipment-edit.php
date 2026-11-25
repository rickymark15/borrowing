<?php require_once '../includes/layout.php';
require_admin();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$it = null;
if ($id) { $s = db()->prepare('SELECT * FROM items WHERE id = ?'); $s->execute([$id]); $it = $s->fetch(); if (!$it) { flash('Equipment not found.', 'error'); redirect('equipment.php'); } }
$v = $it ?? ['name' => '', 'summary' => '', 'item_condition' => 'Good', 'weight_limit' => 'Not applicable', 'pickup_location' => '', 'usage_notes' => '', 'image' => 'assets/img/default.svg', 'quantity_total' => 1, 'quantity_available' => 1];
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'summary', 'item_condition', 'weight_limit', 'pickup_location', 'usage_notes'] as $k) $v[$k] = trim($_POST[$k] ?? '');
    $v['quantity_total'] = filter_var($_POST['quantity_total'] ?? '', FILTER_VALIDATE_INT);
    $v['quantity_available'] = filter_var($_POST['quantity_available'] ?? '', FILTER_VALIDATE_INT);
    if ($v['name'] === '') $err['name'] = 'Enter a name.';
    if ($v['summary'] === '') $err['summary'] = 'Enter a short description.';
    if ($v['quantity_total'] === false || $v['quantity_total'] < 0) $err['quantity_total'] = 'Enter a whole number, 0 or more.';
    if ($v['quantity_available'] === false || $v['quantity_available'] < 0) $err['quantity_available'] = 'Enter a whole number, 0 or more.';
    elseif ($v['quantity_total'] !== false && $v['quantity_available'] > $v['quantity_total']) $err['quantity_available'] = 'Available cannot be more than the total.';

    $newImage = null;
    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        $mime = ($f['error'] === UPLOAD_ERR_OK) ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if (!$ext || $f['size'] > 2 * 1024 * 1024) $err['image'] = 'Upload a JPG, PNG, WebP or GIF under 2 MB.';
        else {
            $name = 'uploads/' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], __DIR__ . '/../' . $name)) $newImage = $name;
            else $err['image'] = 'Could not save the image. Check that the uploads folder is writable.';
        }
    }
    if (!$err) {
        if ($newImage) {
            if ($it && strpos($it['image'], 'uploads/') === 0) @unlink(__DIR__ . '/../' . $it['image']);
            $v['image'] = $newImage;
        }
        $status = ($v['quantity_available'] > 0) ? 'available' : 'borrowed';
        if ($it) {
            db()->prepare('UPDATE equipment SET name=?, summary=?, description=?, item_condition=?, weight_limit=?, pickup_location=?, usage_notes=?, image=?, quantity_total=?, quantity_available=?, status=? WHERE id=?')
                ->execute([$v['name'], $v['summary'], $v['summary'], $v['item_condition'], $v['weight_limit'], $v['pickup_location'], $v['usage_notes'], $v['image'], $v['quantity_total'], $v['quantity_available'], $status, $id]);
            flash('Equipment updated.');
        } else {
            db()->prepare('INSERT INTO equipment (name, summary, description, category, item_condition, weight_limit, pickup_location, usage_notes, image, quantity_total, quantity_available, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$v['name'], $v['summary'], $v['summary'], 'General', $v['item_condition'], $v['weight_limit'], $v['pickup_location'], $v['usage_notes'], $v['image'], $v['quantity_total'], $v['quantity_available'], $status]);
            flash('Equipment added.');
        }
        redirect('equipment.php');
    }
}
$f = fn($k) => isset($err[$k]) ? '<span class="err">' . e($err[$k]) . '</span>' : '';
admin_header($it ? 'Edit equipment' : 'Add equipment', $it ? 'equip' : 'add'); ?>
<div class="head-row"><h1><?= $it ? 'Edit ' . e($it['name']) : 'Add equipment' ?></h1><a href="equipment.php">Back to equipment</a></div>
<form class="panel formgrid" method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="full"><label for="name">Name</label><input id="name" name="name" type="text" required value="<?= e($v['name']) ?>"><?= $f('name') ?></div>
  <div class="full"><label for="summary">Short description</label><input id="summary" name="summary" type="text" required value="<?= e($v['summary']) ?>"><?= $f('summary') ?></div>
  <div><label for="quantity_total">Total quantity</label><input id="quantity_total" name="quantity_total" type="number" min="0" required value="<?= e((string)$v['quantity_total']) ?>"><?= $f('quantity_total') ?></div>
  <div><label for="quantity_available">Available quantity</label><input id="quantity_available" name="quantity_available" type="number" min="0" required value="<?= e((string)$v['quantity_available']) ?>"><?= $f('quantity_available') ?></div>
  <div><label for="item_condition">Condition</label>
    <select id="item_condition" name="item_condition"><?php foreach (['Excellent', 'Good', 'Fair', 'Needs repair'] as $c): ?><option<?= $v['item_condition'] === $c ? ' selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
  <div><label for="weight_limit">Weight limit</label><input id="weight_limit" name="weight_limit" type="text" value="<?= e($v['weight_limit']) ?>"></div>
  <div class="full"><label for="pickup_location">Pickup location</label><input id="pickup_location" name="pickup_location" type="text" value="<?= e($v['pickup_location']) ?>"></div>
  <div class="full"><label for="usage_notes">Usage notes</label><textarea id="usage_notes" name="usage_notes" rows="3"><?= e($v['usage_notes']) ?></textarea></div>
  <div class="full imgrow">
    <img class="detail-img sm" src="../<?= e(item_img($v)) ?>" alt="Current picture">
    <div><label for="image"><?= $it ? 'Replace picture' : 'Picture' ?> (JPG, PNG, WebP or GIF, max 2 MB)</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><?= $f('image') ?></div>
  </div>
  <div class="full"><button class="btn big" type="submit"><?= $it ? 'Save changes' : 'Add equipment' ?></button></div>
</form>
<?php admin_footer(); ?>
