<?php
require '../config.php';
require_login('../');
require_owner();
$root = '../';

$groups = [
    'login'     => ['Log-ins & lockouts', ['login', 'login_locked', 'user_unlock']],
    'orders'    => ['Orders', ['order_status', 'order_cancel', 'order_void', 'order_refund', 'order_delete']],
    'products'  => ['Products & prices', ['product_add', 'product_edit', 'product_delete', 'price_change', 'category_add', 'category_rename', 'category_delete']],
    'users'     => ['Users & roles', ['user_edit', 'user_role', 'user_password_reset', 'user_delete', 'ownership_transfer']],
    'settings'  => ['Settings & maintenance', ['settings_update', 'maintenance_on', 'maintenance_off']],
    'exports'   => ['Downloads', ['export_csv']],
];
$g = $_GET['g'] ?? '';
$who = trim($_GET['who'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;

$where = ['1=1']; $params = [];
if (isset($groups[$g])) {
    $in = $groups[$g][1];
    $where[] = 'action IN (' . implode(',', array_fill(0, count($in), '?')) . ')';
    array_push($params, ...$in);
}
if ($who !== '') { $where[] = '(username LIKE ? OR detail LIKE ?)'; $params[] = "%$who%"; $params[] = "%$who%"; }
$W = implode(' AND ', $where);

$cnt = db()->prepare("SELECT COUNT(*) FROM activity_log WHERE $W");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$st = db()->prepare("SELECT * FROM activity_log WHERE $W ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();

$qs = fn(array $extra) => http_build_query(array_filter(['g' => $g, 'who' => $who] + $extra, fn($v) => $v !== '' && $v !== null));
$title = 'Admin · Activity log';
$adminActive = 'activity';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Activity log</h1><p class="muted"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></p></div>
  <?php include '../includes/admin_nav.php'; ?>

  <div class="chips">
    <a class="chip <?= $g === '' ? 'on' : '' ?>" href="activity.php?<?= e($qs(['g' => ''])) ?>">Everything</a>
    <?php foreach ($groups as $k => [$label]): ?><a class="chip <?= $g === $k ? 'on' : '' ?>" href="activity.php?<?= e($qs(['g' => $k])) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </div>
  <form method="get" class="card inline-card">
    <input type="hidden" name="g" value="<?= e($g) ?>">
    <div class="inline-form">
      <input type="text" name="who" value="<?= e($who) ?>" placeholder="Search a username or text, e.g. admin2 or order #12" maxlength="60">
      <button type="submit" class="btn">Search</button>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap"><?= e(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td>
          <td class="nowrap"><?php if ($r['username'] !== ''): ?>@<?= e($r['username']) ?> <small class="muted"><?= e($r['role']) ?></small><?php else: ?><small class="muted">—</small><?php endif; ?></td>
          <td><span class="badge st-pending"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
          <td><?= e($r['detail']) ?> <small class="muted"><?= e($r['ip']) ?></small></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="center">Nothing logged yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="chips" style="margin-top:1rem">
      <?php if ($page > 1): ?><a class="chip" href="activity.php?<?= e($qs(['page' => $page - 1])) ?>">&larr; Newer</a><?php endif; ?>
      <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="chip" href="activity.php?<?= e($qs(['page' => $page + 1])) ?>">Older &rarr;</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <p class="muted small">The log cannot be edited or cleared from the website, so nobody can hide what they did. Times are server time.</p>
</section>
<?php include '../includes/footer.php'; ?>
