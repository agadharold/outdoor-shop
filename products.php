<?php
require 'config.php';
$q    = trim($_GET['q'] ?? '');
$cat  = trim($_GET['cat'] ?? '');
$sort = $_GET['sort'] ?? 'new';
$orders = [
  'new'        => 'p.id DESC',
  'price_asc'  => 'p.price ASC',
  'price_desc' => 'p.price DESC',
  'name'       => 'p.name ASC',
];
$orderBy = $orders[$sort] ?? $orders['new'];

$where = ['1=1']; $params = [];
if ($q !== '')   { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat !== '') { $where[] = 'c.slug = ?'; $params[] = $cat; }

$st = db()->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy");
$st->execute($params);
$products = $st->fetchAll();
$cats = categories();

$heading = 'All products';
foreach ($cats as $c) if ($c['slug'] === $cat) $heading = $c['name'];
if ($q !== '') $heading = 'Results for “' . $q . '”';

$title = $heading;
include 'includes/header.php';
?>
<section class="container section">
  <div class="page-head">
    <div>
      <h1><?= e($heading) ?></h1>
      <p class="muted"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?></p>
    </div>
    <form method="get" class="sort">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
      <label for="sort" class="sr">Sort by</label>
      <select id="sort" name="sort" onchange="this.form.submit()">
        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
      </select>
    </form>
  </div>

  <div class="chips">
    <a class="chip <?= $cat === '' ? 'on' : '' ?>" href="products.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">All</a>
    <?php foreach ($cats as $c): ?>
      <a class="chip <?= $cat === $c['slug'] ? 'on' : '' ?>" href="products.php?cat=<?= e($c['slug']) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$products): ?>
    <div class="empty"><h3>No products found</h3><p>Try a different search or category.</p><a class="btn" href="products.php">View all products</a></div>
  <?php else: ?>
    <div class="products">
      <?php foreach ($products as $p) product_card($p); ?>
    </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
