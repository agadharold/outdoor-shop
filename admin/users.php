<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'unlock') {
        if (!is_owner()) { flash('Only the owner can unlock accounts.', 'err'); redirect('users.php'); }
        $st = db()->prepare("SELECT username, email FROM users WHERE id = ?");
        $st->execute([(int)($_POST['id'] ?? 0)]);
        if ($t = $st->fetch()) {
            login_clear_failures($t['username'], $t['email']);
            log_activity('user_unlock', "Unlocked @{$t['username']}");
            flash("@{$t['username']} unlocked.");
        }
    }
    redirect('users.php');
}

$users = db()->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders FROM users u ORDER BY u.is_owner DESC, u.is_admin DESC, u.is_staff DESC, u.id")->fetchAll();
$locked = array_flip(locked_idents());
$iAmOwner = is_owner();
$title = 'Admin · Users';
$adminActive = 'users';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Admin</h1></div>
  <?php include '../includes/admin_nav.php'; ?>
  <p class="muted"><strong>Owner:</strong> full control (settings, prices, refunds, reports, activity log, admin accounts, deleting).
    <strong>Admin:</strong> manages orders, products (not prices) and customers.
    <strong>Staff:</strong> can only move orders forward.
    Only the owner can change roles.</p>

  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Email</th><th>Joined</th><th>Orders</th><th>Role</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong> <small class="muted">@<?= e($u['username']) ?></small><?= (int)$u['id'] === (int)$me['id'] ? ' <small class="muted">(you)</small>' : '' ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
          <td><?= (int)$u['orders'] ?></td>
          <td>
            <?php $r = role_of($u); ?>
            <span class="badge <?= ['owner' => 'st-shipped', 'admin' => 'st-delivered', 'staff' => 'st-paid', 'customer' => 'st-pending'][$r] ?>"><?= ucfirst($r) ?></span>
            <?php $isLocked = isset($locked[login_ident($u['username'])]); if ($isLocked): ?><span class="badge st-cancelled">Locked</span><?php endif; ?>
          </td>
          <td class="nowrap">
            <?php if (can_manage_user($u)): ?><a class="btn sm" href="user_edit.php?id=<?= (int)$u['id'] ?>">Manage</a><?php else: ?><small class="muted">Owner only</small><?php endif; ?>
            <?php if ($isLocked && $iAmOwner): ?>
              <form method="post" class="inline-form" style="display:inline-flex"><?= csrf_field() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button type="submit" class="btn sm ghost">Unlock</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include '../includes/footer.php'; ?>
