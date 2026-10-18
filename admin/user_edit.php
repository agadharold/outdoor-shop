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
$iAmOwner = is_owner();
if (!can_manage_user($u)) { flash('Only the owner can manage admin accounts.', 'err'); redirect('users.php'); }
$targetIsOwner = !empty($u['is_owner']);
$canChangeRole = $iAmOwner && !$self && !$targetIsOwner;   // only the owner changes roles (customer / staff / admin)
$currentRole = role_of($u);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        if ($self) $errors[] = 'You cannot delete your own account.';
        elseif ($targetIsOwner) $errors[] = 'The owner account cannot be deleted.';
        elseif ((int)$u['orders'] > 0) $errors[] = 'This user has orders, so the account cannot be deleted. You can remove their admin access instead.';
        else {
            require_sudo('user_edit.php?id=' . $id);
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            log_activity('user_delete', "Deleted user @{$u['username']} (" . $currentRole . ')');
            flash('User deleted.');
            redirect('users.php');
        }
    } elseif ($action === 'transfer') {
        $pw = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $pw->execute([$me['id']]);
        if (!$iAmOwner || $self || $targetIsOwner || empty($u['is_admin'])) $errors[] = 'Only the owner can transfer ownership, and only to another admin.';
        elseif (!password_verify($_POST['owner_password'] ?? '', (string)$pw->fetchColumn())) $errors[] = 'Your password is incorrect, so ownership was not transferred.';
        else {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET is_owner = 0 WHERE id = ?")->execute([$me['id']]);
            $pdo->prepare("UPDATE users SET is_owner = 1, is_admin = 1 WHERE id = ?")->execute([$id]);
            $pdo->commit();
            log_activity('ownership_transfer', "Ownership transferred from @{$me['username']} to @{$u['username']}");
            flash('Ownership transferred to ' . $u['name'] . '. You are now an admin.');
            redirect('users.php');
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $newRole = $canChangeRole ? ($_POST['role'] ?? $currentRole) : $currentRole;
        if (!in_array($newRole, ['customer', 'staff', 'admin'], true) && !($targetIsOwner && $newRole === 'owner')) $newRole = $currentRole;
        $isAdmin = $targetIsOwner ? 1 : (int)($newRole === 'admin');
        $isStaff = (int)($newRole === 'staff');

        if ($name === '' || mb_strlen($name) > 80) $errors[] = 'Please enter a name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if ($newPass !== '' && strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';
        if (!$errors) {
            $chk = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $id]);
            if ($chk->fetchColumn()) $errors[] = 'That email is used by another account.';
        }
        $old = ['name' => $u['name'], 'email' => $u['email']];
        $u['name'] = $name; $u['email'] = $email;   // keep what was typed if there is an error
        if (!$errors) {
            // changing a role, or setting another admin's/staff's password, needs a fresh password confirmation
            if ($iAmOwner && !$self && ($newRole !== $currentRole || ($newPass !== '' && $currentRole !== 'customer'))) require_sudo('user_edit.php?id=' . $id);
            $changes = diff_text($old, ['name' => $name, 'email' => $email], ['name' => 'name', 'email' => 'email']);
            db()->prepare("UPDATE users SET name = ?, email = ?, is_admin = ?, is_staff = ? WHERE id = ?")->execute([$name, $email, $isAdmin, $targetIsOwner ? 0 : $isStaff, $id]);
            if ($newPass !== '') {
                db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
                login_clear_failures($u['username'], $old['email'], $email);   // a password reset also unlocks the account
            }
            if ($newRole !== $currentRole) log_activity('user_role', "@{$u['username']}: " . ucfirst($currentRole) . ' → ' . ucfirst($newRole));
            if ($newPass !== '') log_activity('user_password_reset', "Password changed for @{$u['username']} (" . $currentRole . ')');
            if ($changes !== '') log_activity('user_edit', "@{$u['username']}: $changes");
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

    <label for="role">Role</label>
    <?php if ($canChangeRole): ?>
    <select id="role" name="role">
      <option value="customer" <?= $currentRole === 'customer' ? 'selected' : '' ?>>Customer</option>
      <option value="staff" <?= $currentRole === 'staff' ? 'selected' : '' ?>>Staff (can only move orders forward)</option>
      <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Admin (orders, products, customers)</option>
    </select>
    <?php else: ?>
    <p><strong><?= ucfirst($currentRole) ?></strong> <span class="muted">— <?= $targetIsOwner ? 'this is the Owner account' : ($self ? 'you can’t change your own role' : 'only the owner can change roles') ?></span></p>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn lg">Save changes</button>
      <a class="btn lg ghost" href="users.php">Back</a>
    </div>
  </form>

  <?php if (!$self && !$targetIsOwner): ?>
  <form method="post" class="card" onsubmit="return confirm('Delete this user permanently?');">
    <h3>Delete account</h3>
    <p class="muted">Only accounts with no orders can be deleted.</p>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
    <button type="submit" class="btn danger">Delete this user</button>
  </form>
  <?php endif; ?>

  <?php if ($iAmOwner && !$self && !$targetIsOwner && $u['is_admin']): ?>
  <form method="post" class="card" onsubmit="return confirm('Make this person the Owner? You will become a regular admin.');">
    <h3>Transfer ownership</h3>
    <p class="muted">Makes <strong><?= e($u['name']) ?></strong> the Owner and turns you into a regular admin. Enter your password to confirm.</p>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="transfer">
    <label for="op">Your password</label>
    <input type="password" id="op" name="owner_password" required autocomplete="current-password">
    <button type="submit" class="btn danger">Transfer ownership</button>
  </form>
  <?php endif; ?>
</section>
<?php include '../includes/footer.php'; ?>
