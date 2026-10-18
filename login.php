<?php
require 'config.php';
$next = safe_path($_GET['next'] ?? $_POST['next'] ?? '', 'index.php');
if (current_user()) redirect($next);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = trim($_POST['username'] ?? '');
    $st = db()->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $st->execute([$id, $id]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        login_user($u);
        redirect($next);
    }
    $error = 'Invalid username or password.';
}
$title = 'Log in';
include 'includes/header.php';
?>
<section class="auth">
  <form method="post" class="card auth-card">
    <h1>Welcome back</h1>
    <p class="muted">Log in to checkout and track your orders.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label for="username">Username or email</label>
    <input type="text" id="username" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <button type="submit" class="btn block lg">Log in</button>
    <p class="center muted">New here? <a href="register.php?next=<?= urlencode($next) ?>">Create an account</a></p>
  </form>
</section>
<?php include 'includes/footer.php'; ?>
