<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';
$me = current_user();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders FROM users u WHERE u.id = ?");
$st->execute([$id]);
$u = $st->fetch();
if (!$u) { flash('User not found.', 'err'); redirect('users.php'); }
$self = $id === (int)$me['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? 'save') === 'delete') {
        if ($self) $errors[] = 'You cannot delete your own account.';
        elseif ((int)$u['orders'] > 0) $errors[] = 'This user has orders, so the account cannot be deleted. You can remove their admin access instead.';
        else {
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            flash('User deleted.');
            redirect('users.php');
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $isAdmin = $self ? (int)$u['is_admin'] : (isset($_POST['is_admin']) ? 1 : 0);

        if ($name === '' || mb_strlen($name) > 80) $errors[] = 'Please enter a name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if ($newPass !== '' && strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';
        if (!$errors) {
            $chk = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $id]);
            if ($chk->fetchColumn()) $errors[] = 'That email is used by another account.';
        }
        $u['name'] = $name; $u['email'] = $email; $u['is_admin'] = $isAdmin;
        if (!$errors) {
            db()->prepare("UPDATE users SET name = ?, email = ?, is_admin = ? WHERE id = ?")->execute([$name, $email, $isAdmin, $id]);
            if ($newPass !== '') db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
            if ($self) { $_SESSION['user']['name'] = $name; $_SESSION['user']['email'] = $email; }
            flash('User updated.' . ($newPass !== '' ? ' Password changed.' : ''));
            redirect('users.php');
        }
    }
}

$title = 'Admin · Manage user';
$adminActive = 'users';
include '../includes/header.php';
?>
<section class="container section narrow">
  <div class="page-head"><h1>Manage user</h1></div>
  <?php include '../includes/admin_nav.php'; ?>

  <form method="post" class="card">
    <?php foreach ($errors as $er): ?><p class="error"><?= e($er) ?></p><?php endforeach; ?>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
    <p class="muted">Username: <strong>@<?= e($u['username']) ?></strong> · <?= (int)$u['orders'] ?> order(s)</p>

    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= e($u['name']) ?>" required>
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required>
    <label for="np">Set a new password (leave empty to keep the current one)</label>
    <input type="password" id="np" name="new_password" minlength="8" autocomplete="new-password">

    <label class="check"><input type="checkbox" name="is_admin" value="1" <?= $u['is_admin'] ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>> Admin (full control of the shop)<?= $self ? ' — you can’t change your own role' : '' ?></label>

    <div class="form-actions">
      <button type="submit" class="btn lg">Save changes</button>
      <a class="btn lg ghost" href="users.php">Back</a>
    </div>
  </form>

  <?php if (!$self): ?>
  <form method="post" class="card" onsubmit="return confirm('Delete this user permanently?');">
    <h3>Delete account</h3>
    <p class="muted">Only accounts with no orders can be deleted.</p>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
    <button type="submit" class="btn danger">Delete this user</button>
  </form>
  <?php endif; ?>
</section>
<?php include '../includes/footer.php'; ?>
