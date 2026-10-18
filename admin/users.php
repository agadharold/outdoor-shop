<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';
$me = current_user();

$users = db()->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders FROM users u ORDER BY u.is_admin DESC, u.id")->fetchAll();
$title = 'Admin · Users';
$adminActive = 'users';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Admin</h1></div>
  <?php include '../includes/admin_nav.php'; ?>
  <p class="muted">Admins have full control of orders, products, categories, users and settings. Only give admin access to people you trust.</p>

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
          <td><?= $u['is_admin'] ? '<span class="badge st-delivered">Admin</span>' : '<span class="badge st-pending">Customer</span>' ?></td>
          <td><a class="btn sm" href="user_edit.php?id=<?= (int)$u['id'] ?>">Manage</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include '../includes/footer.php'; ?>
