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
    // Failures are counted per ACCOUNT (its username), so trying the email address instead cannot dodge the lock.
    $ident = login_ident($u ? $u['username'] : $id);
    $wait = login_lock_minutes($ident);
    if ($wait > 0) {
        // locked: do not even check the password
        $error = "Too many failed log-in attempts. Please try again in $wait minute" . ($wait === 1 ? '' : 's') . '.';
    } else {
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
            login_clear_failures($u['username']);
            login_user($u);
            $role = role_of($u);
            if ($role !== 'customer') log_activity('login', 'Logged in', ['id' => $u['id'], 'username' => $u['username'], 'role' => $role]);
            redirect($next);
        }
        login_record_failure($ident);
        $error = 'Invalid username or password.';
        if (login_lock_minutes($ident) > 0) {
            $error = 'Too many failed log-in attempts. This account is locked for ' . LOGIN_WINDOW_MIN . ' minutes.';
            // only record locks on staff/admin/owner accounts (so strangers guessing random names cannot fill the log)
            if ($u && role_of($u) !== 'customer') log_activity('login_locked', 'Account locked after ' . LOGIN_MAX_FAILS . ' wrong passwords', ['id' => $u['id'], 'username' => $u['username'], 'role' => role_of($u)]);
        }
    }
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
