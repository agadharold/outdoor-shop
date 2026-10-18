<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id  = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $st = db()->prepare("SELECT name, stock FROM products WHERE id = ?");
        $st->execute([$id]);
        $back = safe_path($_POST['back'] ?? '', 'cart.php');
        if ($p = $st->fetch()) {
            $have = $_SESSION['cart'][$id] ?? 0;
            $new  = min($have + $qty, (int)$p['stock']);
            if ($new <= $have) {
                flash($p['stock'] > 0 ? "You already have the maximum available of {$p['name']} in your cart." : "{$p['name']} is out of stock.", 'err');
            } else {
                $_SESSION['cart'][$id] = $new;
                flash("{$p['name']} added to cart!");
            }
        }
        redirect($back);
    }

    if ($action === 'update') {
        if (isset($_POST['remove'])) {
            unset($_SESSION['cart'][(int)$_POST['remove']]);
            flash('Item removed.');
        } else {
            foreach (($_POST['qty'] ?? []) as $id => $q) {
                $id = (int)$id; $q = (int)$q;
                $st = db()->prepare("SELECT stock FROM products WHERE id = ?");
                $st->execute([$id]);
                $stock = (int)($st->fetchColumn() ?: 0);
                $q = min($q, $stock);
                if ($q <= 0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id] = $q;
            }
        }
        redirect('cart.php');
    }
}

$c = cart_items();
$pct = $c['subtotal'] > 0 ? min(100, (int)round($c['subtotal'] / FREE_SHIPPING_MIN * 100)) : 0;
$title = 'Your Cart';
include 'includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Shopping cart</h1><p class="muted"><?= $c['count'] ?> item<?= $c['count'] === 1 ? '' : 's' ?></p></div>

  <?php if (!$c['items']): ?>
    <div class="empty"><?= icon('cart', 40) ?><h3>Your cart is empty</h3><p>Looks like you haven't added anything yet.</p><a class="btn" href="products.php">Start shopping</a></div>
  <?php else: ?>
  <div class="cart-layout">
    <div>
      <div class="ship-progress card-lite">
        <?php if ($c['shipping'] > 0): ?>
          <p>Add <strong><?= peso(FREE_SHIPPING_MIN - $c['subtotal']) ?></strong> more to get <strong>free shipping</strong>!</p>
        <?php else: ?>
          <p><?= icon('check', 16) ?> You've unlocked <strong>free shipping</strong>!</p>
        <?php endif; ?>
        <div class="bar-track"><div class="bar-fill" style="width: <?= $pct ?>%"></div></div>
      </div>

      <form method="post" class="plain" id="cart-form" data-autosubmit>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <button type="submit" class="default-submit" tabindex="-1" aria-hidden="true">Update</button>
        <div class="cart-list">
          <?php foreach ($c['items'] as $i): ?>
            <div class="cart-item">
              <a class="ci-img" href="product.php?id=<?= (int)$i['id'] ?>"><img src="assets/images/<?= e($i['image']) ?>" alt=""></a>
              <div class="ci-info">
                <a class="ci-name" href="product.php?id=<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a>
                <span class="muted"><?= peso($i['unit']) ?> each<?php if (on_sale($i)): ?> <s class="was"><?= peso($i['price']) ?></s><?php endif; ?></span>
              </div>
              <div class="stepper">
                <button type="button" data-step="-1" aria-label="Less">&minus;</button>
                <input type="number" name="qty[<?= (int)$i['id'] ?>]" value="<?= $i['qty'] ?>" min="0" max="<?= (int)$i['stock'] ?>" aria-label="Quantity">
                <button type="button" data-step="1" aria-label="More">+</button>
              </div>
              <div class="ci-line"><?= peso($i['line']) ?></div>
              <button type="submit" name="remove" value="<?= (int)$i['id'] ?>" class="icon-x" title="Remove" aria-label="Remove <?= e($i['name']) ?>"><?= icon('x', 18) ?></button>
            </div>
          <?php endforeach; ?>
        </div>
        <noscript><button type="submit" class="btn outline">Update cart</button></noscript>
      </form>
      <p><a href="products.php">&larr; Continue shopping</a></p>
    </div>

    <aside class="summary">
      <h3>Order summary</h3>
      <div class="line"><span>Subtotal</span><span><?= peso($c['subtotal']) ?></span></div>
      <div class="line"><span>Shipping</span><span><?= $c['shipping'] ? peso($c['shipping']) : 'Free' ?></span></div>
      <div class="line total"><span>Total</span><span><?= peso($c['total']) ?></span></div>
      <a href="checkout.php" class="btn block lg">Proceed to checkout</a>
      <p class="muted small center"><?= icon('cash', 14) ?> Pay with cash on delivery</p>
    </aside>
  </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
