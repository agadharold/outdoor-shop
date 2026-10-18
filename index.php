<?php
require 'config.php';
$featured = db()->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.featured = 1 ORDER BY p.id LIMIT 4")->fetchAll();
$cats = categories();
$heroImgs = array_slice(array_column($featured, 'image'), 0, 3);
$title = 'Home';
include 'includes/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="pill">Hiking · Camping · Trekking</span>
      <h1>Gear up for your next <span>adventure</span></h1>
      <p>Quality outdoor essentials, delivered to your door. Order online and pay in cash when it arrives.</p>
      <div class="hero-cta">
        <a class="btn lg light" href="products.php">Shop now</a>
        <a class="btn lg outline" href="#how">How it works</a>
      </div>
      <ul class="hero-points">
        <li><?= icon('truck', 18) ?> Free shipping over <?= peso(FREE_SHIPPING_MIN) ?></li>
        <li><?= icon('cash', 18) ?> Cash on delivery</li>
      </ul>
    </div>
    <div class="hero-art" aria-hidden="true">
      <?php foreach ($heroImgs as $i => $img): ?>
        <div class="hero-card c<?= $i + 1 ?>"><img src="assets/images/<?= e($img) ?>" alt=""></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="container trust">
  <div><?= icon('truck', 26) ?><div><strong>Free shipping</strong><span>On orders over <?= peso(FREE_SHIPPING_MIN) ?></span></div></div>
  <div><?= icon('cash', 26) ?><div><strong>Cash on delivery</strong><span>Pay when your order arrives</span></div></div>
  <div><?= icon('box', 26) ?><div><strong>Track your order</strong><span>Follow it from placed to delivered</span></div></div>
  <div><?= icon('check', 26) ?><div><strong>Live stock</strong><span>See what's available right now</span></div></div>
</section>

<section class="container section">
  <div class="section-head"><h2>Shop by category</h2><a href="products.php">View all &rarr;</a></div>
  <div class="cat-grid">
    <?php foreach ($cats as $c): ?>
      <a class="cat-tile" href="products.php?cat=<?= e($c['slug']) ?>">
        <span class="ct-img"><img src="assets/images/<?= e($c['image']) ?>" alt="" loading="lazy"></span>
        <strong><?= e($c['name']) ?></strong>
        <span class="muted"><?= (int)$c['n'] ?> item<?= $c['n'] == 1 ? '' : 's' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section">
  <div class="section-head"><h2>Popular gear</h2><a href="products.php">Shop all &rarr;</a></div>
  <div class="products">
    <?php foreach ($featured as $p) product_card($p); ?>
  </div>
</section>

<section class="container section" id="how">
  <div class="section-head center-head"><h2>How ordering works</h2></div>
  <div class="steps">
    <div class="step"><span class="num">1</span><h3>Pick your gear</h3><p>Browse the shop and add what you need to your cart.</p></div>
    <div class="step"><span class="num">2</span><h3>Enter your address</h3><p>Create a free account and tell us where to deliver.</p></div>
    <div class="step"><span class="num">3</span><h3>Pay on delivery</h3><p>Pay in cash when your order arrives. No online payment needed.</p></div>
  </div>
</section>

<section class="container">
  <div class="cta-banner">
    <div><h2>Ready for the trail?</h2><p>Free shipping on orders over <?= peso(FREE_SHIPPING_MIN) ?>.</p></div>
    <a class="btn lg light" href="products.php">Start shopping</a>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
