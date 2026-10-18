</main>
<?php $root = $root ?? ''; $fcats = categories(); $fu = current_user(); ?>
<footer class="site-footer">
  <div class="container foot-grid">
    <div>
      <a class="brand light" href="<?= $root ?>index.php"><span class="logo"><svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="m3 19 6-11 4 7 2.5-4L21 19z"/></svg></span><span class="brand-name"><?= brand_html() ?></span></a>
      <p>Hiking, camping and trekking essentials. Order online, pay when it arrives.</p>
    </div>
    <div>
      <h4>Shop</h4>
      <?php foreach ($fcats as $footCat): ?><a href="<?= $root ?>products.php?cat=<?= e($footCat['slug']) ?>"><?= e($footCat['name']) ?></a><?php endforeach; ?>
    </div>
    <div>
      <h4>My account</h4>
      <?php if ($fu): ?>
        <a href="<?= $root ?>orders.php">My orders</a>
        <a href="<?= $root ?>account.php">Account settings</a>
      <?php else: ?>
        <a href="<?= $root ?>login.php">Log in</a>
        <a href="<?= $root ?>register.php">Create account</a>
      <?php endif; ?>
      <a href="<?= $root ?>cart.php">Cart</a>
    </div>
    <div>
      <h4>Ordering</h4>
      <span>Payment: Cash on Delivery</span>
      <span>Shipping: <?= peso(SHIPPING_FEE) ?> flat</span>
      <span>Free over <?= peso(FREE_SHIPPING_MIN) ?></span>
    </div>
  </div>
  <div class="container copy">&copy; <?= date('Y') ?> <?= SHOP_NAME ?>. All rights reserved.</div>
</footer>
<script src="<?= $root ?>assets/script.js"></script>
</body>
</html>
