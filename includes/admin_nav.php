<?php
$adminActive = $adminActive ?? '';
$__role = current_role();
$__tabs = ['orders' => ['Orders', 'orders.php', 'staff']];
if (in_array($__role, ['admin', 'owner'], true)) {
    $__tabs += ['products' => ['Products', 'products.php', 'admin'], 'categories' => ['Categories', 'categories.php', 'admin'], 'users' => ['Users', 'users.php', 'admin']];
}
if ($__role === 'owner') {
    $__tabs += ['reports' => ['Reports', 'reports.php', 'owner'], 'activity' => ['Activity log', 'activity.php', 'owner'], 'settings' => ['Settings', 'settings.php', 'owner']];
}
?>
<div class="chips">
  <?php foreach ($__tabs as $__k => [$__label, $__href]): ?>
    <a class="chip <?= $adminActive === $__k ? 'on' : '' ?>" href="<?= $__href ?>"><?= $__label ?></a>
  <?php endforeach; ?>
</div>
