<?php require_once '../includes/layout.php';
require_admin();
$items = db()->query('SELECT * FROM items ORDER BY name')->fetchAll();
admin_header('Equipment', 'equip'); ?>
<div class="head-row"><h1>Equipment</h1><a class="btn" href="equipment-edit.php">Add equipment</a></div>
<div class="scroll"><table>
  <caption class="sr">All equipment and available quantity</caption>
  <thead><tr><th scope="col">Equipment</th><th scope="col">Available</th><th scope="col">Total</th><th scope="col">Condition</th><th scope="col">Pickup location</th><th scope="col"><span class="sr">Edit</span></th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <th scope="row"><span class="thumb-row"><img class="thumb" src="../<?= e(item_img($it)) ?>" alt=""><?= e($it['name']) ?></span></th>
      <td><?= stock_badge($it) ?></td><td><?= (int)$it['quantity_total'] ?></td>
      <td><?= e($it['item_condition']) ?></td><td><?= e($it['pickup_location']) ?></td>
      <td><a class="btn small" href="equipment-edit.php?id=<?= (int)$it['id'] ?>">Edit<span class="sr"> <?= e($it['name']) ?></span></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php admin_footer(); ?>
