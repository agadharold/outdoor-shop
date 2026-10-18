<?php
require 'config.php';
require_login();
$u = current_user();
$msgs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
    $st->execute([$u['id']]);
    $hash = $st->fetchColumn();
    $new = $_POST['new_password'] ?? '';
    if (!password_verify($_POST['current_password'] ?? '', (string)$hash)) $msgs[] = 'Current password is incorrect.';
    elseif (strlen($new) < 8) $msgs[] = 'New password must be at least 8 characters.';
    elseif ($new !== ($_POST['new_password2'] ?? '')) $msgs[] = 'New passwords do not match.';
    else {
        db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('Password changed.');
        redirect('account.php');
    }
}
$title = 'My Account';
include 'includes/header.php';
?>
<section class="container section narrow">
  <div class="page-head"><h1>My account</h1></div>
  <div class="card profile">
    <span class="avatar lg"><?= e(strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
    <div>
      <h3><?= e($u['name']) ?></h3>
      <p class="muted">@<?= e($u['username']) ?> · <?= e($u['email']) ?></p>
      <p class="muted small">Member since <?= e(date('F j, Y', strtotime($u['created_at']))) ?> · <a href="orders.php">View my orders</a></p>
    </div>
  </div>

  <form method="post" class="card">
    <h3>Change password</h3>
    <?php foreach ($msgs as $m): ?><p class="error"><?= e($m) ?></p><?php endforeach; ?>
    <?= csrf_field() ?>
    <label for="cp">Current password</label>
    <input type="password" id="cp" name="current_password" required>
    <label for="np">New password</label>
    <input type="password" id="np" name="new_password" required minlength="8">
    <label for="np2">Confirm new password</label>
    <input type="password" id="np2" name="new_password2" required>
    <button type="submit" class="btn">Update password</button>
  </form>
</section>
<?php include 'includes/footer.php'; ?>
