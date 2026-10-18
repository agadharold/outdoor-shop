<?php $adminActive = $adminActive ?? ''; ?>
<div class="chips">
  <a class="chip <?= $adminActive === 'orders' ? 'on' : '' ?>" href="orders.php">Orders</a>
  <a class="chip <?= $adminActive === 'products' ? 'on' : '' ?>" href="products.php">Products</a>
  <a class="chip <?= $adminActive === 'categories' ? 'on' : '' ?>" href="categories.php">Categories</a>
  <a class="chip <?= $adminActive === 'users' ? 'on' : '' ?>" href="users.php">Users</a>
  <a class="chip <?= $adminActive === 'settings' ? 'on' : '' ?>" href="settings.php">Settings</a>
</div>
