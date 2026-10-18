<?php
require 'config.php';
$next = safe_path($_GET['next'] ?? $_POST['next'] ?? '', 'index.php');
if (current_user()) redirect($next);

$errors = []; $v = ['name' => '', 'username' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['name'] = trim($_POST['name'] ?? '');
    $v['username'] = trim($_POST['username'] ?? '');
    $v['email'] = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($v['name'] === '' || mb_strlen($v['name']) > 80) $errors[] = 'Please enter your name.';
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $v['username'])) $errors[] = 'Username must be 3–30 letters, numbers or underscores.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== ($_POST['password2'] ?? '')) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $st = db()->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $st->execute([$v['username'], $v['email']]);
        if ($st->fetchColumn()) $errors[] = 'That username or email is already registered.';
    }
    if (!$errors) {
        $pdo = db();
        $pdo->prepare("INSERT INTO users (username, name, email, password_hash) VALUES (?,?,?,?)")
            ->execute([$v['username'], $v['name'], $v['email'], password_hash($pass, PASSWORD_DEFAULT)]);
        $st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $st->execute([(int)$pdo->lastInsertId()]);
        login_user($st->fetch());
        flash('Welcome to ' . SHOP_NAME . ', ' . explode(' ', $v['name'])[0] . '!');
        redirect($next);
    }
}
$title = 'Create account';
include 'includes/header.php';
?>
<section class="auth">
  <form method="post" class="card auth-card">
    <h1>Create your account</h1>
    <p class="muted">It only takes a minute.</p>
    <?php foreach ($errors as $er): ?><p class="error"><?= e($er) ?></p><?php endforeach; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" required value="<?= e($v['name']) ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required value="<?= e($v['username']) ?>">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required value="<?= e($v['email']) ?>">
    <label for="password">Password (min 8 characters)</label>
    <input type="password" id="password" name="password" required minlength="8">
    <label for="password2">Confirm password</label>
    <input type="password" id="password2" name="password2" required>
    <button type="submit" class="btn block lg">Create account</button>
    <p class="center muted">Already registered? <a href="login.php?next=<?= urlencode($next) ?>">Log in</a></p>
  </form>
</section>
<?php include 'includes/footer.php'; ?>
