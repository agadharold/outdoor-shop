<?php
require '../config.php';
require_login('../');
require_owner();
$root = '../';
$me = current_user();
$next = safe_path($_GET['next'] ?? $_POST['next'] ?? '', 'orders.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ident = login_ident($me['username']);
    $wait = login_lock_minutes($ident);
    if ($wait > 0) {
        $error = "Too many wrong passwords. Try again in $wait minute" . ($wait === 1 ? '' : 's') . '.';
    } else {
        $st = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $st->execute([$me['id']]);
        if (password_verify($_POST['password'] ?? '', (string)$st->fetchColumn())) {
            login_clear_failures($me['username'], $me['email']);
            $_SESSION['sudo_until'] = time() + SUDO_SECONDS;
            flash('Password confirmed. You have ' . (SUDO_SECONDS / 60) . ' minutes to do dangerous actions.');
            redirect($next);
        }
        login_record_failure($ident);
        $error = 'That password is not correct.';
    }
}

$title = 'Confirm your password';
$adminActive = '';
include '../includes/header.php';
?>
<section class="container section narrow">
  <div class="page-head"><h1>Confirm your password</h1></div>
  <form method="post" class="card">
    <p class="muted">Deleting things, giving or removing admin access and refunds are protected. Enter your password to unlock them for <?= SUDO_SECONDS / 60 ?> minutes.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <label for="pw">Your password</label>
    <input type="password" id="pw" name="password" required autofocus autocomplete="current-password">
    <div class="form-actions">
      <button type="submit" class="btn lg">Confirm</button>
      <a class="btn lg ghost" href="<?= e($next) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php include '../includes/footer.php'; ?>
