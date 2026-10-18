<?php
require '../config.php';
require_login('../');
require_owner();
$root = '../';

db()->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) PRIMARY KEY, v VARCHAR(255) NOT NULL) ENGINE=InnoDB");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['shop_name'] ?? '');
    $fee  = trim((string)($_POST['shipping_fee'] ?? ''));
    $free = trim((string)($_POST['free_shipping_min'] ?? ''));
    $ann  = trim($_POST['announcement'] ?? '');
    $maint = isset($_POST['maintenance']) ? '1' : '0';
    $maintMsg = trim($_POST['maintenance_message'] ?? '');

    if ($name === '' || mb_strlen($name) > 60) $errors[] = 'Shop name is required (max 60 characters).';
    if (!is_numeric($fee) || (float)$fee < 0) $errors[] = 'Shipping fee must be 0 or more.';
    if (!is_numeric($free) || (float)$free < 0) $errors[] = 'Free-shipping minimum must be 0 or more.';
    if (mb_strlen($ann) > 150) $errors[] = 'Announcement is too long (max 150 characters).';
    if (mb_strlen($maintMsg) > 200) $errors[] = 'Maintenance message is too long (max 200 characters).';

    if (!$errors) {
        $st = db()->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
        $new = ['shop_name' => $name, 'shipping_fee' => $fee, 'free_shipping_min' => $free, 'announcement' => $ann, 'maintenance' => $maint, 'maintenance_message' => $maintMsg];
        $old = ['shop_name' => SHOP_NAME, 'shipping_fee' => SHIPPING_FEE, 'free_shipping_min' => FREE_SHIPPING_MIN, 'announcement' => ANNOUNCEMENT, 'maintenance' => MAINTENANCE ? '1' : '0', 'maintenance_message' => trim($__settings['maintenance_message'] ?? '')];
        foreach ($new as $k => $v) $st->execute([$k, $v]);
        if ($old['maintenance'] !== $maint) log_activity($maint === '1' ? 'maintenance_on' : 'maintenance_off', $maint === '1' ? 'Shop closed to customers' : 'Shop re-opened');
        $changes = diff_text($old, array_diff_key($new, ['maintenance' => 1]), ['shop_name' => 'shop name', 'shipping_fee' => 'shipping fee', 'free_shipping_min' => 'free shipping over', 'announcement' => 'announcement', 'maintenance_message' => 'maintenance message']);
        if ($changes !== '') log_activity('settings_update', $changes);
        flash('Settings saved.');
        redirect('settings.php');
    }
}

$title = 'Admin · Settings';
$adminActive = 'settings';
include '../includes/header.php';
?>
<section class="container section narrow">
  <div class="page-head"><h1>Admin</h1></div>
  <?php include '../includes/admin_nav.php'; ?>

  <form method="post" class="card">
    <h3>Shop settings</h3>
    <?php foreach ($errors as $er): ?><p class="error"><?= e($er) ?></p><?php endforeach; ?>
    <?= csrf_field() ?>
    <label for="shop_name">Shop name</label>
    <input type="text" id="shop_name" name="shop_name" value="<?= e($_POST['shop_name'] ?? SHOP_NAME) ?>" required maxlength="60">

    <div class="row-2">
      <div>
        <label for="shipping_fee">Shipping fee (₱)</label>
        <input type="number" id="shipping_fee" name="shipping_fee" min="0" step="0.01" value="<?= e($_POST['shipping_fee'] ?? SHIPPING_FEE) ?>" required>
      </div>
      <div>
        <label for="free_shipping_min">Free shipping over (₱)</label>
        <input type="number" id="free_shipping_min" name="free_shipping_min" min="0" step="0.01" value="<?= e($_POST['free_shipping_min'] ?? FREE_SHIPPING_MIN) ?>" required>
      </div>
    </div>

    <label for="announcement">Top-bar message (optional)</label>
    <input type="text" id="announcement" name="announcement" maxlength="150" value="<?= e($_POST['announcement'] ?? ANNOUNCEMENT) ?>" placeholder="Leave empty to show the free-shipping message">
    <p class="muted small">Example: “Sale this weekend – 10% off all tents!”</p>

    <h3>Maintenance mode</h3>
    <label class="check"><input type="checkbox" name="maintenance" value="1" <?= (isset($_POST['shop_name']) ? isset($_POST['maintenance']) : MAINTENANCE) ? 'checked' : '' ?>> Close the shop to customers (staff, admins and the owner can still use it)</label>
    <label for="maintenance_message">Message customers see (optional)</label>
    <input type="text" id="maintenance_message" name="maintenance_message" maxlength="200" value="<?= e($_POST['maintenance_message'] ?? trim($__settings['maintenance_message'] ?? '')) ?>" placeholder="We're making some improvements and will be back shortly.">

    <button type="submit" class="btn lg">Save settings</button>
  </form>
</section>
<?php include '../includes/footer.php'; ?>
