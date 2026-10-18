<?php
require '../config.php';
require_login('../');
require_staff();
$root = '../';
$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'refunded'];
$role = current_role();
$iAmOwner = $role === 'owner';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['order_id'] ?? 0);
    $back = 'orders.php' . (in_array($_POST['filter'] ?? '', $statuses, true) ? '?status=' . $_POST['filter'] : '');
    $pdo = db();

    if (($_POST['action'] ?? '') === 'delete') {
        if (!$iAmOwner) { flash('Only the owner can delete orders.', 'err'); redirect($back); }
        require_sudo($back);
        $st = $pdo->prepare("SELECT status, total FROM orders WHERE id = ?");
        $st->execute([$id]);
        $o = $st->fetch();
        if ($o && in_array($o['status'], ['cancelled', 'refunded'], true)) {
            $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);   // order_items are removed too
            log_activity('order_delete', "Deleted {$o['status']} order #$id (" . peso($o['total']) . ')');
            flash("Order #$id deleted.");
        } else {
            flash('Only cancelled or refunded orders can be deleted.', 'err');
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
        elseif ($old === $new) { $pdo->rollBack(); }
        elseif (!in_array($new, order_transitions($old, $role), true)) {
            $pdo->rollBack();
            flash($role === 'owner' ? 'That change is not allowed here. Use “Refund / void” for paid orders.'
                  : 'You can only move an order forward' . ($role === 'admin' ? ' (or cancel it while it is still pending). Refunds are for the owner.' : '.'), 'err');
        } else {
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$new, $id]);
            if ($new === 'cancelled') {   // put stock back
                $pdo->prepare("UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock + i.qty WHERE i.order_id = ?")->execute([$id]);
            }
            $pdo->commit();
            log_activity($new === 'cancelled' ? 'order_cancel' : 'order_status', "Order #$id: " . status_label($old) . ' → ' . status_label($new));
            flash("Order #$id set to " . status_label($new) . ".");
        }
    }
    redirect($back);
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$stats = db()->query("SELECT COUNT(*) AS n, COALESCE(SUM(status = 'pending'), 0) AS pending,
                      COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','refunded') THEN total END), 0) AS revenue FROM orders")->fetch();
$low = (int)db()->query("SELECT COUNT(*) FROM products WHERE stock <= 5")->fetchColumn();
$st = db()->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id WHERE (? = '' OR o.status = ?) ORDER BY o.id DESC");
$st->execute([$filter, $filter]);
$orders = $st->fetchAll();
$title = 'Admin · Orders';
$adminActive = 'orders';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1><?= $role === 'staff' ? 'Orders' : 'Admin' ?></h1></div>
  <?php include '../includes/admin_nav.php'; ?>

  <div class="stat-grid">
    <div class="stat"><span>Total orders</span><strong><?= (int)$stats['n'] ?></strong></div>
    <div class="stat"><span>Pending</span><strong><?= (int)$stats['pending'] ?></strong></div>
    <?php if ($role !== 'staff'): ?><div class="stat"><span>Sales (not cancelled or refunded)</span><strong><?= peso($stats['revenue']) ?></strong></div><?php endif; ?>
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
      <?php foreach ($orders as $o): $options = order_transitions($o['status'], $role); ?>
        <tr>
          <td><a href="../order.php?id=<?= $o['id'] ?>">#<?= $o['id'] ?></a></td>
          <td><?= e($o['full_name']) ?> <small class="muted">(@<?= e($o['username']) ?>)</small></td>
          <td><?= e(date('M j, Y', strtotime($o['created_at']))) ?></td>
          <td><?= peso($o['total']) ?></td>
          <td>
            <?php if ($options): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>">
              <select name="status" aria-label="Order status">
                <option value="<?= e($o['status']) ?>" selected><?= e(status_label($o['status'])) ?></option>
                <?php foreach ($options as $s): ?><option value="<?= $s ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" class="btn sm">Save</button>
            </form>
            <?php else: ?>
              <?= status_badge($o['status']) ?>
              <?php if ($o['refund_reason']): ?><small class="muted" title="<?= e($o['refund_reason']) ?>">· reason on file</small><?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($iAmOwner && in_array($o['status'], ['paid', 'shipped', 'delivered'], true)): ?>
              <a class="btn sm danger" href="refund.php?id=<?= $o['id'] ?>">Refund / void</a>
            <?php endif; ?>
            <?php if ($iAmOwner && in_array($o['status'], ['cancelled', 'refunded'], true)): ?>
              <form method="post" class="inline-form" onsubmit="return confirm('Permanently delete this order?');">
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
  <?php if ($role !== 'owner'): ?><p class="muted small">Orders can only be moved forward<?= $role === 'admin' ? ', or cancelled while pending' : '' ?>. Refunds and voiding paid orders are done by the owner.</p><?php endif; ?>
</section>
<?php include '../includes/footer.php'; ?>
