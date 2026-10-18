<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = ['name' => '', 'description' => '', 'details' => '', 'price' => '', 'stock' => 10, 'category_id' => 0, 'featured' => 0, 'image' => ''];
if ($id) {
    $st = db()->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash('Product not found.', 'err'); redirect('products.php'); }
    $p = $row;
}
$cats = db()->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p['name']        = trim($_POST['name'] ?? '');
    $p['description'] = trim($_POST['description'] ?? '');
    $p['details']     = trim($_POST['details'] ?? '');
    $p['price']       = trim((string)($_POST['price'] ?? ''));
    $p['stock']       = trim((string)($_POST['stock'] ?? ''));
    $p['featured']    = isset($_POST['featured']) ? 1 : 0;
    $p['category_id'] = (int)($_POST['category_id'] ?? 0);
    $newCat           = trim($_POST['new_category'] ?? '');

    if ($p['name'] === '' || mb_strlen($p['name']) > 100) $errors[] = 'Name is required (max 100 characters).';
    if ($p['description'] === '' || mb_strlen($p['description']) > 255) $errors[] = 'Short description is required (max 255 characters).';
    if (!is_numeric($p['price']) || (float)$p['price'] < 0) $errors[] = 'Enter a valid price.';
    if (!ctype_digit($p['stock'])) $errors[] = 'Stock must be a whole number (0 or more).';

    // category: pick one, or type a new one
    if ($newCat === '') {
        $chk = db()->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
        $chk->execute([$p['category_id']]);
        if (!$chk->fetchColumn()) $errors[] = 'Please choose a category (or type a new one).';
    } elseif (mb_strlen($newCat) > 80) {
        $errors[] = 'New category name is too long.';
    }

    // image upload
    $newImage = null; $tmp = null;
    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed (error code ' . (int)$f['error'] . ').';
        } elseif ($f['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Image is too big (max 3 MB).';
        } else {
            $info = @getimagesize($f['tmp_name']);
            $mime = is_array($info) ? ($info['mime'] ?? '') : '';
            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
            if (!$ext) $errors[] = 'Image must be a JPG, PNG, WEBP or GIF.';
            else {
                $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($p['name'])), '-') ?: 'product';
                $newImage = $base . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $tmp = $f['tmp_name'];
            }
        }
    } elseif (!$id) {
        $errors[] = 'Please choose a product image.';
    }

    if (!$errors) {
        $pdo = db();
        if ($newCat !== '') {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($newCat)), '-') ?: 'category';
            $pdo->prepare("INSERT IGNORE INTO categories (slug, name) VALUES (?, ?)")->execute([$slug, $newCat]);
            $g = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
            $g->execute([$slug]);
            $p['category_id'] = (int)$g->fetchColumn();
        }
        if ($newImage !== null && !move_uploaded_file($tmp, __DIR__ . '/../assets/images/' . $newImage)) {
            $errors[] = 'Could not save the image. Check that the assets/images folder is writable.';
        } else {
            if ($newImage !== null) $p['image'] = $newImage;
            if ($id) {
                $pdo->prepare("UPDATE products SET category_id=?, name=?, description=?, details=?, price=?, stock=?, image=?, featured=? WHERE id=?")
                    ->execute([$p['category_id'], $p['name'], $p['description'], $p['details'] ?: null, $p['price'], (int)$p['stock'], $p['image'], $p['featured'], $id]);
                flash('Product updated.');
            } else {
                $pdo->prepare("INSERT INTO products (category_id, name, description, details, price, stock, image, featured) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$p['category_id'], $p['name'], $p['description'], $p['details'] ?: null, $p['price'], (int)$p['stock'], $p['image'], $p['featured']]);
                flash('Product added.');
            }
            redirect('products.php');
        }
    }
}

$title = $id ? 'Edit product' : 'Add product';
$adminActive = 'products';
include '../includes/header.php';
?>
<section class="container section narrow">
  <div class="page-head"><h1><?= $id ? 'Edit product' : 'Add product' ?></h1></div>
  <?php include '../includes/admin_nav.php'; ?>

  <form method="post" enctype="multipart/form-data" class="card">
    <?php foreach ($errors as $er): ?><p class="error"><?= e($er) ?></p><?php endforeach; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <label for="name">Product name *</label>
    <input type="text" id="name" name="name" value="<?= e($p['name']) ?>" required maxlength="100">

    <label for="description">Short description *</label>
    <input type="text" id="description" name="description" value="<?= e($p['description']) ?>" required maxlength="255">

    <label for="details">Full details (optional)</label>
    <textarea id="details" name="details" rows="4"><?= e($p['details']) ?></textarea>

    <div class="row-2">
      <div>
        <label for="price">Price (₱) *</label>
        <input type="number" id="price" name="price" step="0.01" min="0" value="<?= e($p['price']) ?>" required>
      </div>
      <div>
        <label for="stock">Stock *</label>
        <input type="number" id="stock" name="stock" min="0" step="1" value="<?= e($p['stock']) ?>" required>
      </div>
    </div>

    <div class="row-2">
      <div>
        <label for="category_id">Category *</label>
        <select id="category_id" name="category_id">
          <option value="0">— choose —</option>
          <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="new_category">…or new category</label>
        <input type="text" id="new_category" name="new_category" placeholder="e.g. Clothing" value="<?= e($_POST['new_category'] ?? '') ?>">
      </div>
    </div>

    <label for="image">Product image <?= $id ? '(leave empty to keep the current one)' : '*' ?></label>
    <?php if ($p['image']): ?><img class="img-preview" src="../assets/images/<?= e($p['image']) ?>" alt="Current image"><?php endif; ?>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    <p class="muted small">JPG, PNG, WEBP or GIF, max 3 MB. A photo on a white background looks best.</p>

    <label class="check"><input type="checkbox" name="featured" value="1" <?= $p['featured'] ? 'checked' : '' ?>> Show as “Popular” on the home page</label>

    <div class="form-actions">
      <button type="submit" class="btn lg"><?= $id ? 'Save changes' : 'Add product' ?></button>
      <a class="btn lg ghost" href="products.php">Cancel</a>
    </div>
  </form>
</section>
<?php include '../includes/footer.php'; ?>
