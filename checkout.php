<?php
require 'config.php';
require_login();
$c = cart_items();
if (!$c['items']) { flash('Your cart is empty!', 'err'); redirect('cart.php'); }

$errors = [];
$u = current_user();
$v = ['name' => $u['name'], 'address' => '', 'contact' => '', 'notes' => ''];

// pre-fill from the customer's previous order
$st = db()->prepare("SELECT full_name, address, contact FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$st->execute([$u['id']]);
if ($last = $st->fetch()) { $v['name'] = $last['full_name']; $v['address'] = $last['address']; $v['contact'] = $last['contact']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'address', 'contact', 'notes'] as $k) $v[$k] = trim((string)($_POST[$k] ?? ''));

    if ($v['name'] === '' || $v['address'] === '' || $v['contact'] === '') $errors[] = 'Please fill out all required fields.';
    if ($v['contact'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $v['contact'])) $errors[] = 'Please enter a valid contact number.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $cart = $_SESSION['cart'];
            $ids = array_map('intval', array_keys($cart));
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id IN ($in) FOR UPDATE");
            $st->execute($ids);
            $rows = [];
            foreach ($st->fetchAll() as $r) $rows[(int)$r['id']] = $r;

            $subtotal = 0.0;
            foreach ($cart as $id => $qty) {
                $r = $rows[(int)$id] ?? null;
                if (!$r) throw new RuntimeException('A product in your cart is no longer available.');
                if ((int)$r['stock'] < $qty) throw new RuntimeException("Sorry, only {$r['stock']} of {$r['name']} left in stock.");
                $subtotal += $qty * (float)$r['price'];
            }
            $shipping = shipping_for($subtotal);
            $total = $subtotal + $shipping;

            $pdo->prepare("INSERT INTO orders (user_id, full_name, address, contact, payment_method, notes, subtotal, shipping_fee, total) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$u['id'], $v['name'], $v['address'], $v['contact'], 'cod', $v['notes'] ?: null, $subtotal, $shipping, $total]);
            $orderId = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?,?,?,?,?)");
            $dec = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            foreach ($cart as $id => $qty) {
                $r = $rows[(int)$id];
                $ins->execute([$orderId, $id, $r['name'], $r['price'], $qty]);
                $dec->execute([$qty, $id]);
            }
            $pdo->commit();
            $_SESSION['cart'] = [];
            redirect("order.php?id=$orderId&placed=1");
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Could not place your order. Please try again.';
        }
    }
}

$title = 'Checkout';
include 'includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Checkout</h1></div>
  <div class="cart-layout">
    <form method="post" class="card">
      <?php foreach ($errors as $er): ?><p class="error"><?= e($er) ?></p><?php endforeach; ?>
      <?= csrf_field() ?>

      <h3 class="step-title"><span class="num">1</span> Delivery details</h3>
      <label for="name">Full name *</label>
      <input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required autocomplete="name">

      <label for="address">Delivery address *</label>
      <input type="text" id="address" name="address" placeholder="House no., street, barangay, city, province" value="<?= e($v['address']) ?>" required autocomplete="street-address">

      <label for="contact">Contact number *</label>
      <input type="tel" id="contact" name="contact" placeholder="09XX XXX XXXX" value="<?= e($v['contact']) ?>" required autocomplete="tel">

      <label for="notes">Order notes (optional)</label>
      <input type="text" id="notes" name="notes" placeholder="Landmarks, delivery instructions…" value="<?= e($v['notes']) ?>">

      <h3 class="step-title"><span class="num">2</span> Payment</h3>
      <div class="pay-option">
        <?= icon('cash', 28) ?>
        <div>
          <strong>Cash on Delivery</strong>
          <span>Pay in cash when your order arrives. Please prepare <strong><?= peso($c['total']) ?></strong>.</span>
        </div>
        <span class="selected"><?= icon('check', 18) ?></span>
      </div>

      <button type="submit" class="btn block lg">Place order · <?= peso($c['total']) ?></button>
      <p class="muted small center">By placing your order you agree to pay the total on delivery.</p>
    </form>

    <aside class="summary">
      <h3>Your order</h3>
      <?php foreach ($c['items'] as $i): ?>
        <div class="sum-item">
          <img src="assets/images/<?= e($i['image']) ?>" alt="">
          <span><?= e($i['name']) ?> <small class="muted">× <?= $i['qty'] ?></small></span>
          <span><?= peso($i['line']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="line"><span>Subtotal</span><span><?= peso($c['subtotal']) ?></span></div>
      <div class="line"><span>Shipping</span><span><?= $c['shipping'] ? peso($c['shipping']) : 'Free' ?></span></div>
      <div class="line total"><span>Total</span><span><?= peso($c['total']) ?></span></div>
      <a href="cart.php" class="center-link">Edit cart</a>
    </aside>
  </div>
</section>
<?php include 'includes/footer.php'; ?>
