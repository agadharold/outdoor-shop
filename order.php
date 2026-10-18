<?php
require 'config.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o || ($o['user_id'] != current_user()['id'] && !is_admin())) {
    http_response_code(404); exit('Order not found.');
}
$it = db()->prepare("SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$it->execute([$id]);
$items = $it->fetchAll();

$steps = ['pending' => 'Order placed', 'paid' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$keys = array_keys($steps);
$cur = array_search($o['status'], $keys, true);
$placed = isset($_GET['placed']);

$title = "Order #$id";
include 'includes/header.php';
?>
<section class="container section narrow">
  <?php if ($placed): ?>
    <div class="success-banner">
      <span class="ok-icon"><?= icon('check', 28) ?></span>
      <div>
        <h1>Thank you! Your order is placed.</h1>
        <p>Order #<?= $id ?> is being processed. Please prepare <strong><?= peso($o['total']) ?></strong> in cash for when it arrives.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="page-head"><h1>Order #<?= $id ?></h1> <?= status_badge($o['status']) ?></div>
  <?php endif; ?>
  <p class="muted">Placed on <?= e(date('F j, Y g:i A', strtotime($o['created_at']))) ?></p>

  <?php if ($o['status'] === 'cancelled'): ?>
    <div class="error">This order was cancelled.</div>
  <?php else: ?>
    <ol class="timeline">
      <?php foreach ($keys as $n => $k): ?>
        <li class="<?= $n < $cur ? 'done' : ($n === $cur ? 'now' : '') ?>"><span class="dotm"><?= $n <= $cur ? icon('check', 14) : '' ?></span><span><?= e($steps[$k]) ?></span></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <div class="order-grid">
    <div class="card-lite">
      <h3>Items</h3>
      <?php foreach ($items as $i): ?>
        <div class="sum-item">
          <?php if ($i['image']): ?><img src="assets/images/<?= e($i['image']) ?>" alt=""><?php else: ?><span></span><?php endif; ?>
          <span><?= e($i['name']) ?> <small class="muted">× <?= $i['qty'] ?></small></span>
          <span><?= peso($i['price'] * $i['qty']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="line"><span>Subtotal</span><span><?= peso($o['subtotal']) ?></span></div>
      <div class="line"><span>Shipping</span><span><?= $o['shipping_fee'] > 0 ? peso($o['shipping_fee']) : 'Free' ?></span></div>
      <div class="line total"><span>Total (pay on delivery)</span><span><?= peso($o['total']) ?></span></div>
    </div>

    <div class="card-lite">
      <h3>Delivery details</h3>
      <p><strong><?= e($o['full_name']) ?></strong></p>
      <p><?= icon('pin', 16) ?> <?= e($o['address']) ?></p>
      <p>Contact: <?= e($o['contact']) ?></p>
      <?php if ($o['notes']): ?><p class="muted">Notes: <?= e($o['notes']) ?></p><?php endif; ?>
      <h3>Payment</h3>
      <p><?= icon('cash', 16) ?> Cash on Delivery</p>
    </div>
  </div>
  <p><a href="orders.php">&larr; All my orders</a> &nbsp;·&nbsp; <a href="products.php">Continue shopping</a></p>
</section>
<?php include 'includes/footer.php'; ?>
