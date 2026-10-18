<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';
$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['order_id'] ?? 0);
    $back = 'orders.php' . (in_array($_POST['filter'] ?? '', $statuses, true) ? '?status=' . $_POST['filter'] : '');
    $pdo = db();

    if (($_POST['action'] ?? '') === 'delete') {
        $st = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $st->execute([$id]);
        if ($st->fetchColumn() === 'cancelled') {
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);   // order_items are removed too
            flash("Order #$id deleted.");
        } else {
            flash('Only cancelled orders can be deleted.', 'err');
        }
        redirect($back);
    }

    $new = $_POST['status'] ?? '';
    if (in_array($new, $statuses, true)) {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $st->execute([$id]);
        $old = $st->fetchColumn();
        if ($old === false) { $pdo->rollBack(); flash('Order not found.', 'err'); }
        elseif ($old === 'cancelled' && $new !== 'cancelled') { $pdo->rollBack(); flash('Cancelled orders cannot be reopened.', 'err'); }
        else {
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$new, $id]);
            if ($new === 'cancelled' && $old !== 'cancelled') {   // put stock back
                $pdo->prepare("UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock + i.qty WHERE i.order_id = ?")->execute([$id]);
            }
            $pdo->commit();
            flash("Order #$id set to " . status_label($new) . ".");
        }
    }
    redirect($back);
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$stats = db()->query("SELECT COUNT(*) AS n, COALESCE(SUM(status = 'pending'), 0) AS pending,
                      COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total END), 0) AS revenue FROM orders")->fetch();
$low = (int)db()->query("SELECT COUNT(*) FROM products WHERE stock <= 5")->fetchColumn();
$st = db()->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id WHERE (? = '' OR o.status = ?) ORDER BY o.id DESC");
$st->execute([$filter, $filter]);
$orders = $st->fetchAll();
$title = 'Admin · Orders';
$adminActive = 'orders';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Admin</h1></div>
  <?php include '../includes/admin_nav.php'; ?>

  <div class="stat-grid">
    <div class="stat"><span>Total orders</span><strong><?= (int)$stats['n'] ?></strong></div>
    <div class="stat"><span>Pending</span><strong><?= (int)$stats['pending'] ?></strong></div>
    <div class="stat"><span>Sales (not cancelled)</span><strong><?= peso($stats['revenue']) ?></strong></div>
    <div class="stat"><span>Low-stock products</span><strong><?= $low ?></strong></div>
  </div>

  <div class="chips">
    <a class="chip <?= $filter === '' ? 'on' : '' ?>" href="orders.php">All</a>
    <?php foreach ($statuses as $s): ?><a class="chip <?= $filter === $s ? 'on' : '' ?>" href="orders.php?status=<?= $s ?>"><?= e(status_label($s)) ?></a><?php endforeach; ?>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total (COD)</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><a href="../order.php?id=<?= $o['id'] ?>">#<?= $o['id'] ?></a></td>
          <td><?= e($o['full_name']) ?> <small class="muted">(@<?= e($o['username']) ?>)</small></td>
          <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td><?= peso($o['total']) ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>">
              <select name="status">
                <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn sm">Save</button>
            </form>
          </td>
          <td>
            <?php if ($o['status'] === 'cancelled'): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('Permanently delete this cancelled order?');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>">
                <button type="submit" class="btn sm danger">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="6" class="center">No orders found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include '../includes/footer.php'; ?>
