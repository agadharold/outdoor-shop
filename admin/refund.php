<?php
require '../config.php';
require_login('../');
require_owner();
$root = '../';

$id = (int)($_GET['id'] ?? $_POST['order_id'] ?? 0);
$st = db()->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { flash('Order not found.', 'err'); redirect('orders.php'); }
if (!in_array($o['status'], ['paid', 'shipped', 'delivered'], true)) { flash('Only paid, shipped or delivered orders can be refunded or voided.', 'err'); redirect('orders.php'); }
$canVoid = in_array($o['status'], ['paid', 'shipped'], true);   // a delivered order can only be refunded
$errors = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') require_sudo('refund.php?id=' . $id);   // ask for the password before showing the form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kind = $_POST['kind'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $restock = isset($_POST['restock']);
    if (!in_array($kind, ['void', 'refund'], true) || ($kind === 'void' && !$canVoid)) $errors[] = 'Choose what to do with this order.';
    if ($reason === '' || mb_strlen($reason) > 255) $errors[] = 'Please give a reason (max 255 characters).';
    if (!$errors) {
        require_sudo('refund.php?id=' . $id);
        $pdo = db();
        $pdo->beginTransaction();
        $lk = $pdo->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $lk->execute([$id]);
        $cur = $lk->fetchColumn();
        if (!in_array($cur, ['paid', 'shipped', 'delivered'], true)) { $pdo->rollBack(); flash('This order was already changed by someone else.', 'err'); redirect('orders.php'); }
        $new = $kind === 'void' ? 'cancelled' : 'refunded';
        $pdo->prepare("UPDATE orders SET status = ?, refund_reason = ? WHERE id = ?")->execute([$new, $reason, $id]);
        $back = $kind === 'void' || $restock;
        if ($back) $pdo->prepare("UPDATE products p JOIN order_items i ON i.product_id = p.id SET p.stock = p.stock + i.qty WHERE i.order_id = ?")->execute([$id]);
        $pdo->commit();
        log_activity($kind === 'void' ? 'order_void' : 'order_refund',
            "Order #$id (" . status_label($cur) . ', ' . peso($o['total']) . ') ' . ($kind === 'void' ? 'voided' : 'refunded') . ($back ? ', items back in stock' : ', stock unchanged') . '. Reason: ' . $reason);
        flash("Order #$id " . ($kind === 'void' ? 'voided' : 'refunded') . '.');
        redirect('orders.php');
    }
}

$title = 'Refund / void order';
$adminActive = 'orders';
include '../includes/header.php';
?>
<section class="container section narrow">
  <div class="page-head"><h1>Refund / void order #<?= $id ?></h1></div>
  <?php include '../includes/admin_nav.php'; ?>
  <form method="post" class="card">
    <p class="muted">Order #<?= $id ?> · <?= e($o['full_name']) ?> (@<?= e($o['username']) ?>) · <?= peso($o['total']) ?> · currently <?= status_badge($o['status']) ?></p>
    <?php foreach ($errors as $er): ?><p class="error"><?= e($er) ?></p><?php endforeach; ?>
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>">

    <label class="check"><input type="radio" name="kind" value="refund" <?= ($_POST['kind'] ?? 'refund') === 'refund' ? 'checked' : '' ?>> Refund: mark as Refunded (money is returned to the customer)</label>
    <?php if ($canVoid): ?><label class="check"><input type="radio" name="kind" value="void" <?= ($_POST['kind'] ?? '') === 'void' ? 'checked' : '' ?>> Void: cancel the order (items always go back in stock)</label><?php endif; ?>

    <label class="check"><input type="checkbox" name="restock" value="1" <?= $o['status'] === 'delivered' ? '' : 'checked' ?>> For a refund: put the items back in stock</label>

    <label for="reason">Reason (kept in the order and in the activity log) *</label>
    <input type="text" id="reason" name="reason" maxlength="255" required value="<?= e($_POST['reason'] ?? '') ?>" placeholder="e.g. Item arrived damaged">
    <p class="muted small">This cannot be undone. You will be asked to confirm your password.</p>
    <div class="form-actions">
      <button type="submit" class="btn danger lg">Continue</button>
      <a class="btn lg ghost" href="orders.php">Cancel</a>
    </div>
  </form>
</section>
<?php include '../includes/footer.php'; ?>
