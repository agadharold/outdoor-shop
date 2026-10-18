<?php
$root = $root ?? '';
$user = current_user();
$cartCount = array_sum($_SESSION['cart'] ?? []);
$page = basename($_SERVER['SCRIPT_NAME']);
$flash = pull_flash();
$navCats = categories();
$activeCat = $_GET['cat'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= SHOP_NAME ?> – hiking, camping and trekking gear. Order online and pay on delivery.">
  <title><?= e($title ?? 'Shop') ?> | <?= SHOP_NAME ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='12' r='12' fill='%2315803d'/%3E%3Cpath d='m5 17 4.5-8 3 5 2-3L19 17z' fill='white'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="<?= $root ?>assets/style.css">
</head>
<body>
<?php if (MAINTENANCE && is_staff_member()): ?>
<div class="notice maint-notice" role="status">
  <div class="notice-in"><span class="notice-icon"><?= icon('wrench', 16) ?></span>
    <span class="notice-text"><strong>Maintenance mode is on.</strong> Customers can't see the shop right now, but you can because you're staff.</span></div>
</div>
<?php endif; ?>
<div class="notice" id="notice" data-key="<?= e(substr(md5(ANNOUNCEMENT . '|' . FREE_SHIPPING_MIN), 0, 8)) ?>" hidden>
  <div class="notice-in">
  <?php if (ANNOUNCEMENT !== ''): ?>
    <span class="notice-icon accent"><?= icon('megaphone', 16) ?></span>
    <span class="notice-text"><?= e(ANNOUNCEMENT) ?></span>
  <?php else: ?>
    <span class="notice-pill"><?= icon('truck', 16) ?> Free shipping over <?= peso(FREE_SHIPPING_MIN) ?></span>
    <span class="notice-pill"><?= icon('cash', 16) ?> Pay on delivery</span>
  <?php endif; ?>
  </div>
  <button type="button" class="notice-x" id="notice-x" aria-label="Dismiss"><?= icon('x', 16) ?></button>
</div>
<script>try{var n=document.getElementById('notice');if(sessionStorage.getItem('notice-'+n.dataset.key)!=='x')n.hidden=false;}catch(e){document.getElementById('notice').hidden=false;}</script>

<header class="site-header">
  <div class="bar container">
    <button class="icon-btn menu-btn" id="menu-btn" aria-label="Menu" aria-expanded="false"><?= icon('menu', 24) ?></button>

    <a class="brand" href="<?= $root ?>index.php">
      <span class="logo"><svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="m3 19 6-11 4 7 2.5-4L21 19z"/></svg></span>
      <span class="brand-name"><?= brand_html() ?></span>
    </a>

    <form class="search" action="<?= $root ?>products.php" method="get" role="search">
      <?= icon('search', 18) ?>
      <input type="search" name="q" placeholder="Search tents, boots, backpacks…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search products">
      <button type="submit">Search</button>
    </form>

    <div class="actions">
      <?php if ($user): ?>
        <details class="dropdown">
          <summary>
            <span class="avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            <span class="who"><?= e(explode(' ', $user['name'])[0]) ?></span>
            <?php if (current_role() !== 'customer'): ?><span class="role-chip role-<?= e(current_role()) ?>"><?= e(role_label(current_role())) ?></span><?php endif; ?>
            <?= icon('chevron', 16) ?>
          </summary>
          <div class="menu">
            <a href="<?= $root ?>orders.php">My orders</a>
            <a href="<?= $root ?>account.php">My account</a>
            <?php if (is_staff_member()): ?><a href="<?= $root ?>admin/orders.php">Admin panel</a><?php endif; ?>
            <a href="<?= $root ?>logout.php" class="danger">Log out</a>
          </div>
        </details>
      <?php else: ?>
        <a class="link-plain" href="<?= $root ?>login.php"><?= icon('user', 20) ?> <span>Log in</span></a>
        <a class="btn sm hide-sm" href="<?= $root ?>register.php">Sign up</a>
      <?php endif; ?>
      <a class="cart-btn" href="<?= $root ?>cart.php" aria-label="Cart">
        <?= icon('cart', 24) ?>
        <?php if ($cartCount > 0): ?><span class="badge-count"><?= $cartCount ?></span><?php endif; ?>
      </a>
    </div>
  </div>

  <nav class="catnav" id="catnav" aria-label="Categories">
    <div class="container">
      <a href="<?= $root ?>products.php" class="<?= $page === 'products.php' && $activeCat === '' ? 'on' : '' ?>">All products</a>
      <?php foreach ($navCats as $navCat): ?>
        <a href="<?= $root ?>products.php?cat=<?= e($navCat['slug']) ?>" class="<?= $page === 'products.php' && $activeCat === $navCat['slug'] ? 'on' : '' ?>"><?= e($navCat['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

<?php if ($flash): ?><div class="flash <?= e($flash[1]) ?>" role="status"><?= e($flash[0]) ?></div><?php endif; ?>
<main>
