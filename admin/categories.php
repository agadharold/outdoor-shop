<?php
require '../config.php';
require_login('../');
require_admin();
$root = '../';
$pdo = db();

function make_slug(string $s): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-') ?: 'category'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($action === 'add') {
        if ($name === '' || mb_strlen($name) > 80) { flash('Enter a category name (max 80 characters).', 'err'); }
        else {
            $st = $pdo->prepare("INSERT IGNORE INTO categories (slug, name) VALUES (?, ?)");
            $st->execute([make_slug($name), $name]);
            flash($st->rowCount() ? "Category “$name” added." : 'A category with that name already exists.', $st->rowCount() ? 'ok' : 'err');
        }
    } elseif ($action === 'rename') {
        if ($name === '' || mb_strlen($name) > 80) { flash('Enter a category name (max 80 characters).', 'err'); }
        else { $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$name, $id]); flash('Category renamed.'); }
    } elseif ($action === 'delete') {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $cnt->execute([$id]);
        $n = (int)$cnt->fetchColumn();
        $moveTo = (int)($_POST['move_to'] ?? 0);
        if ($n > 0) {
            $ok = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ? AND id <> ?");
            $ok->execute([$moveTo, $id]);
            if (!$ok->fetchColumn()) { flash('Choose another category to move its products to.', 'err'); redirect('categories.php'); }
        }
        $pdo->beginTransaction();
        if ($n > 0) $pdo->prepare("UPDATE products SET category_id = ? WHERE category_id = ?")->execute([$moveTo, $id]);
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        $pdo->commit();
        flash('Category deleted.' . ($n > 0 ? " $n product(s) moved." : ''));
    }
    redirect('categories.php');
}

$cats = $pdo->query("SELECT c.*, COUNT(p.id) AS n FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name")->fetchAll();
$title = 'Admin · Categories';
$adminActive = 'categories';
include '../includes/header.php';
?>
<section class="container section">
  <div class="page-head"><h1>Admin</h1></div>
  <?php include '../includes/admin_nav.php'; ?>

  <form method="post" class="card inline-card">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label for="newcat">Add a category</label>
    <div class="inline-form">
      <input type="text" id="newcat" name="name" placeholder="e.g. Clothing" maxlength="80" required>
      <button type="submit" class="btn">Add</button>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Category</th><th>Products</th><th>Delete</th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
              <input type="text" name="name" value="<?= e($c['name']) ?>" maxlength="80" required aria-label="Category name">
              <button type="submit" class="btn sm ghost">Rename</button>
            </form>
          </td>
          <td><?= (int)$c['n'] ?></td>
          <td>
            <form method="post" class="inline-form" onsubmit="return confirm('Delete this category?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
              <?php if ($c['n'] > 0): ?>
                <select name="move_to" required aria-label="Move products to">
                  <option value="">Move its products to…</option>
                  <?php foreach ($cats as $o) if ($o['id'] !== $c['id']): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endif; ?>
                </select>
              <?php endif; ?>
              <button type="submit" class="btn sm danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small">Deleting a category never deletes products. If it has products, you choose where they move to.</p>
</section>
<?php include '../includes/footer.php'; ?>
