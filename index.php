<?php require_once 'includes/layout.php'; page_header('Home', 'home');
$pdo = db();
$types = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$units = (int)$pdo->query('SELECT COALESCE(SUM(quantity_available),0) FROM items')->fetchColumn();
$members = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('user','member')")->fetchColumn();
$items = $pdo->query('SELECT * FROM items ORDER BY quantity_available DESC, name LIMIT 4')->fetchAll();
?>
<section class="hero">
  <div>
    <h1>Borrow what you need. Share what you have.</h1>
    <p>Ladders, drills and wheelbarrows sit idle most of the year. HulamBee lets neighbors borrow from a shared shelf instead of buying new.</p>
    <p class="cta"><a class="btn big" href="browse.php">Browse equipment</a>
      <?php if (!user()): ?><a class="btn big ghost-light" href="register.php">Create free account</a><?php endif; ?></p>
  </div>
  <div class="hero-pics" aria-hidden="true">
    <?php foreach (array_slice($items, 0, 3) as $it): ?><img src="<?= e(item_img($it)) ?>" alt=""><?php endforeach; ?>
  </div>
</section>
<section class="stats">
  <div><strong><?= $types ?></strong> kinds of equipment</div>
  <div><strong><?= $units ?></strong> items ready to borrow</div>
  <div><strong><?= $members ?></strong> members</div>
</section>
<section>
  <h2>Available now</h2>
  <div class="grid"><?php foreach ($items as $it) item_card($it); ?></div>
  <p><a href="browse.php">See all equipment</a></p>
</section>
<section>
  <h2>How it works</h2>
  <ol class="steps">
    <li><strong>Create an account.</strong> It takes a minute and lets us know who is borrowing.</li>
    <li><strong>Pick equipment.</strong> Check what is in stock and send a request with your needed-by date.</li>
    <li><strong>Collect and return.</strong> A coordinator approves your request. Bring the item back when you're done.</li>
  </ol>
</section>
<?php page_footer(); ?>
