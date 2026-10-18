<?php
require 'config.php';
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404); $title = 'Not found'; include 'includes/header.php';
    echo '<section class="container section"><div class="empty"><h3>Product not found</h3><a class="btn" href="products.php">Back to shop</a></div></section>';
    include 'includes/footer.php'; exit;
}

$rel = db()->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.category_id = ? AND p.id <> ? LIMIT 4");
$rel->execute([$p['category_id'], $id]);
$related = $rel->fetchAll();

$stock = (int)$p['stock'];
$title = $p['name'];
include 'includes/header.php';
?>
<section class="container section">
  <p class="crumbs"><a href="index.php">Home</a> / <a href="products.php">Shop</a> / <a href="products.php?cat=<?= e($p['category_slug']) ?>"><?= e($p['category_name']) ?></a> / <?= e($p['name']) ?></p>
  <div class="detail">
    <div class="detail-img"><img src="assets/images/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"></div>
    <div class="detail-info">
      <small class="cat"><?= e($p['category_name']) ?></small>
      <h1><?= e($p['name']) ?></h1>
      <p class="price big"><?= price_html($p) ?><?php if (on_sale($p)): ?> <span class="tag-inline">Sale</span><?php endif; ?></p>
      <p class="lead"><?= e($p['description']) ?></p>
      <?php if ($p['details']): ?><p class="muted"><?= nl2br(e($p['details'])) ?></p><?php endif; ?>
      <p>
        <?php if ($stock <= 0): ?><span class="stock out">Out of stock</span>
        <?php elseif ($stock <= 5): ?><span class="stock low">Only <?= $stock ?> left</span>
        <?php else: ?><span class="stock ok">In stock</span><?php endif; ?>
      </p>
      <form method="post" action="cart.php" class="buy">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= $id ?>">
        <input type="hidden" name="back" value="product.php?id=<?= $id ?>">
        <div class="stepper">
          <button type="button" data-step="-1" aria-label="Less">&minus;</button>
          <input type="number" id="qty" name="qty" value="1" min="1" max="<?= max(1, $stock) ?>" <?= $stock <= 0 ? 'disabled' : '' ?> aria-label="Quantity">
          <button type="button" data-step="1" aria-label="More">+</button>
        </div>
        <button type="submit" class="btn lg grow" <?= $stock <= 0 ? 'disabled' : '' ?>><?= icon('cart', 18) ?> Add to cart</button>
      </form>
      <ul class="perk-list">
        <li><?= icon('cash', 18) ?> Pay with cash on delivery</li>
        <li><?= icon('truck', 18) ?> <?= peso(SHIPPING_FEE) ?> shipping, free over <?= peso(FREE_SHIPPING_MIN) ?></li>
        <li><?= icon('box', 18) ?> Track your order in My Orders</li>
      </ul>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="container section">
  <div class="section-head"><h2>You may also like</h2></div>
  <div class="products"><?php foreach ($related as $r) product_card($r); ?></div>
</section>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
