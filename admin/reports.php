<?php
require '../config.php';
require_login('../');
require_owner();
require '../includes/range.php';
$root = '../';
[$from, $to, $label, $key] = report_range();
$pdo = db();
$P = [$from, $to];
$RANGE = "DATE(o.created_at) BETWEEN ? AND ?";
$LIVE = "o.status NOT IN ('cancelled','refunded')";

$st = $pdo->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(o.total),0) AS sales, COALESCE(AVG(o.total),0) AS avg_order,
                     COALESCE(SUM(o.shipping_fee),0) AS shipping FROM orders o WHERE $RANGE AND $LIVE");
$st->execute($P); $t = $st->fetch();

$st = $pdo->prepare("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE $RANGE AND $LIVE");
$st->execute($P); $units = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT status, COUNT(*) AS n, COALESCE(SUM(total),0) AS total FROM orders o WHERE $RANGE GROUP BY status");
$st->execute($P);
$byStatus = [];
foreach ($st->fetchAll() as $r) $byStatus[$r['status']] = $r;
$collected = (float)($byStatus['delivered']['total'] ?? 0);
$refunded = (float)($byStatus['refunded']['total'] ?? 0);

$days = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
$monthly = $days > 92 || $key === 'all';
$fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
$st = $pdo->prepare("SELECT DATE_FORMAT(o.created_at, '$fmt') AS d, COUNT(*) AS n, SUM(o.total) AS sales FROM orders o WHERE $RANGE AND $LIVE GROUP BY d ORDER BY d DESC LIMIT 120");
$st->execute($P); $series = $st->fetchAll();
$maxSales = max(array_map(fn($r) => (float)$r['sales'], $series) ?: [1]);

$st = $pdo->prepare("SELECT i.name, SUM(i.qty) AS units, SUM(i.qty * i.price) AS revenue FROM order_items i JOIN orders o ON o.id = i.order_id
                     WHERE $RANGE AND $LIVE GROUP BY i.product_id, i.name ORDER BY revenue DESC LIMIT 10");
$st->execute($P); $top = $st->fetchAll();

$q = http_build_query(['from' => $from, 'to' => $to] + ($key === 'all' ? ['range' => 'all'] : []));
$title = 'Admin · Reports';
$adminActive = 'reports';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Reports</h1><p class="muted"><?= e($label) ?></p></div>
  <?php include '../includes/admin_nav.php'; ?>

  <div class="chips">
    <?php foreach (['today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', 'month' => 'This month', 'all' => 'All time'] as $k => $l): ?>
      <a class="chip <?= $key === $k ? 'on' : '' ?>" href="reports.php?range=<?= $k ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="card inline-card">
    <div class="inline-form">
      <label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>" required>
      <label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>" required>
      <button type="submit" class="btn">Show</button>
    </div>
  </form>

  <div class="stat-grid">
    <div class="stat"><span>Sales (not cancelled or refunded)</span><strong><?= peso($t['sales']) ?></strong></div>
    <div class="stat"><span>Orders</span><strong><?= (int)$t['orders'] ?></strong></div>
    <div class="stat"><span>Average order</span><strong><?= peso(round((float)$t['avg_order'], 2)) ?></strong></div>
    <div class="stat"><span>Items sold</span><strong><?= $units ?></strong></div>
    <div class="stat"><span>Delivered (cash collected)</span><strong><?= peso($collected) ?></strong></div>
    <div class="stat"><span>Refunded</span><strong><?= peso($refunded) ?></strong></div>
  </div>

  <div class="two-col">
    <div class="table-wrap">
      <table>
        <thead><tr><th><?= $monthly ? 'Month' : 'Day' ?></th><th>Orders</th><th>Sales</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($series as $r): ?>
          <tr><td><?= e($r['d']) ?></td><td><?= (int)$r['n'] ?></td><td><?= peso($r['sales']) ?></td>
              <td class="bar-cell"><span class="mini-bar" style="width: <?= max(2, round($r['sales'] / $maxSales * 100)) ?>%"></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$series): ?><tr><td colspan="4" class="center">No sales in this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Top products</th><th>Units</th><th>Revenue</th></tr></thead>
          <tbody>
          <?php foreach ($top as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int)$r['units'] ?></td><td><?= peso($r['revenue']) ?></td></tr><?php endforeach; ?>
          <?php if (!$top): ?><tr><td colspan="3" class="center">Nothing sold yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="table-wrap" style="margin-top:1rem">
        <table>
          <thead><tr><th>Orders by status</th><th>Count</th><th>Value</th></tr></thead>
          <tbody>
          <?php foreach (['pending', 'paid', 'shipped', 'delivered', 'cancelled', 'refunded'] as $s): if (empty($byStatus[$s])) continue; ?>
            <tr><td><?= status_badge($s) ?></td><td><?= (int)$byStatus[$s]['n'] ?></td><td><?= peso($byStatus[$s]['total']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$byStatus): ?><tr><td colspan="3" class="center">No orders in this period.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <h3>Download (CSV, opens in Excel)</h3>
    <p class="muted small">Uses the dates above. Orders include customer names, addresses and phone numbers, so keep the files private. Every download is written to the activity log.</p>
    <div class="form-actions">
      <a class="btn" href="export.php?type=orders&amp;<?= $q ?>">Orders</a>
      <a class="btn" href="export.php?type=items&amp;<?= $q ?>">Order items (one line per product)</a>
      <a class="btn ghost" href="export.php?type=products">Products &amp; stock</a>
    </div>
  </div>
</section>
<?php include '../includes/footer.php'; ?>
