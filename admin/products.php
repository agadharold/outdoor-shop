<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        if (!is_owner()) { flash('Only the owner can delete products.', 'err'); redirect('products.php'); }
        require_sudo('products.php');
        $id = (int)($_POST['product_id'] ?? 0);
        $nm = db()->prepare("SELECT name FROM products WHERE id = ?");
        $nm->execute([$id]);
        $name = $nm->fetchColumn();
        db()->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);   // past orders keep their own copy of name/price
        if ($name !== false) log_activity('product_delete', "Deleted product #$id “{$name}”");
        flash('Product deleted.');
    }
    redirect('products.php');
}

$iAmOwner = is_owner();
$products = db()->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC")->fetchAll();
$title = 'Admin · Products';
$adminActive = 'products';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head">
    <h1>Admin</h1>
    <a class="btn" href="product_edit.php">+ Add product</a>
  </div>
  <?php include '../includes/admin_nav.php'; ?>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Popular</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td class="prod-cell"><img class="thumb-sm" src="../assets/images/<?= e($p['image']) ?>" alt=""> <strong><?= e($p['name']) ?></strong></td>
          <td><?= e($p['category_name']) ?></td>
          <td><?= price_html($p) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['featured'] ? 'Yes' : '—' ?></td>
          <td>
            <form method="post" class="inline-form" onsubmit="return confirm('Delete this product? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <a class="btn sm" href="product_edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
              <?php if ($iAmOwner): ?><button type="submit" class="btn sm danger">Delete</button><?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="6" class="center">No products yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$iAmOwner): ?><p class="muted small">Only the owner can delete products. To hide a product from customers, set its stock to 0.</p><?php endif; ?>
</section>
<?php include '../includes/footer.php'; ?>
