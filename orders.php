<?php
require 'config.php';
require_login();
$st = db()->prepare("SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS items FROM orders o WHERE user_id = ? ORDER BY o.id DESC");
$st->execute([current_user()['id']]);
$orders = $st->fetchAll();
$title = 'My Orders';
include 'includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>My orders</h1></div>
  <?php if (!$orders): ?>
    <div class="empty"><?= icon('box', 40) ?><h3>No orders yet</h3><p>When you place an order it will show up here.</p><a class="btn" href="products.php">Start shopping</a></div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong>#<?= $o['id'] ?></strong></td>
          <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td><?= (int)$o['items'] ?></td>
          <td><?= peso($o['total']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td><a href="order.php?id=<?= $o['id'] ?>">View details</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>
