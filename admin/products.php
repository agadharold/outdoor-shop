<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['product_id'] ?? 0);
        db()->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);   // past orders keep their own copy of name/price
        flash('Product deleted.');
    }
    redirect('products.php');
}

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
          <td><?= peso($p['price']) ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['featured'] ? 'Yes' : '—' ?></td>
          <td>
            <form method="post" class="inline-form" onsubmit="return confirm('Delete this product? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <a class="btn sm" href="product_edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
              <button type="submit" class="btn sm danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="6" class="center">No products yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include '../includes/footer.php'; ?>
