<?php
declare(strict_types=1);

function env(string $key, string $default = ''): string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// Defaults match XAMPP; Docker overrides them through environment variables.
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'outdoor_shop'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Shop settings can be changed by an admin (Admin > Settings); these are the fallbacks.
function load_settings(): array {
    try { return db()->query("SELECT k, v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR); }
    catch (Throwable $e) { return []; }   // table/database not created yet
}
$__settings = load_settings();
define('SHOP_NAME', trim($__settings['shop_name'] ?? '') ?: 'Outdoor Boys');
define('SHIPPING_FEE', (float)($__settings['shipping_fee'] ?? 150));
define('FREE_SHIPPING_MIN', (float)($__settings['free_shipping_min'] ?? 5000));
define('ANNOUNCEMENT', trim($__settings['announcement'] ?? ''));

if (PHP_SAPI !== 'cli') {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function peso($n): string {
    $n = (float)$n;
    return '₱' . number_format($n, fmod($n, 1.0) ? 2 : 0);
}

function redirect(string $url): never { header('Location: ' . $url); exit; }

/** Only allow simple relative paths for redirects (blocks open redirects). */
function safe_path(?string $p, string $fallback): string {
    $p = (string)$p;
    if ($p === '' || $p[0] === '/' || str_contains($p, '//') || str_contains($p, ':') || str_contains($p, '..')) return $fallback;
    return preg_match('#^[A-Za-z0-9_\-./?=&%+]+$#', $p) ? $p : $fallback;
}

// ---- icons (inline SVG) ----
function icon(string $name, int $size = 20): string {
    $paths = [
      'cart'   => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
      'user'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
      'menu'   => '<path d="M3 6h18M3 12h18M3 18h18"/>',
      'truck'  => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
      'cash'   => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
      'box'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
      'check'  => '<path d="M20 6 9 17l-5-5"/>',
      'pin'    => '<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
      'chevron'=> '<path d="m6 9 6 6 6-6"/>',
      'x'      => '<path d="M18 6 6 18M6 6l12 12"/>',
    ];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

// ---- auth ----
function current_user(): ?array {
    $u = $_SESSION['user'] ?? null;
    // ignore sessions created by an older version of the site (missing fields)
    if ($u !== null && !isset($u['id'], $u['username'], $u['name'], $u['email'], $u['is_admin'], $u['created_at'])) {
        unset($_SESSION['user']);
        return null;
    }
    return $u;
}
function login_user(array $u): void {
    $cart = $_SESSION['cart'] ?? [];
    session_regenerate_id(true);
    $_SESSION['cart'] = $cart;
    $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['name'],
                         'email' => $u['email'], 'is_admin' => (int)$u['is_admin'], 'created_at' => $u['created_at']];
}
function require_login(string $root = ''): void {
    if (!current_user()) {
        $next = ($root !== '' ? 'admin/' : '') . basename($_SERVER['SCRIPT_NAME']) . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
        redirect($root . 'login.php?next=' . urlencode($next));
    }
}
/** Checks the database (not the session), so removing someone's admin access takes effect immediately. */
function is_admin(): bool {
    $u = current_user();
    if (!$u) return false;
    $st = db()->prepare("SELECT is_admin FROM users WHERE id = ?");
    $st->execute([$u['id']]);
    return (bool)$st->fetchColumn();
}
function require_admin(): void {
    if (!is_admin()) { http_response_code(403); exit('Admins only.'); }
}

function brand_html(): string {
    $w = preg_split('/\s+/', trim(SHOP_NAME));
    $last = array_pop($w);
    return ($w ? e(implode(' ', $w)) . ' ' : '') . '<b>' . e($last) . '</b>';
}

// ---- flash messages ----
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function pull_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400); exit('Invalid or expired form. Go back, refresh and try again.');
    }
}

// ---- shop helpers ----
function shipping_for(float $subtotal): float {
    if ($subtotal <= 0) return 0;
    return $subtotal >= FREE_SHIPPING_MIN ? 0 : SHIPPING_FEE;
}

function cart_items(): array {
    $cart = $_SESSION['cart'] ?? [];
    $out = ['items' => [], 'count' => 0, 'subtotal' => 0.0, 'shipping' => 0.0, 'total' => 0.0];
    if (!$cart) return $out;
    $ids = array_map('intval', array_keys($cart));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM products WHERE id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll() as $p) {
        $p['qty'] = (int)$cart[$p['id']];
        $p['line'] = $p['qty'] * (float)$p['price'];
        $out['items'][] = $p;
        $out['count'] += $p['qty'];
        $out['subtotal'] += $p['line'];
    }
    $out['shipping'] = shipping_for($out['subtotal']);
    $out['total'] = $out['subtotal'] + $out['shipping'];
    return $out;
}

function categories(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query("SELECT c.*, COUNT(p.id) AS n,
            (SELECT x.image FROM products x WHERE x.category_id = c.id ORDER BY x.featured DESC, x.id LIMIT 1) AS image
            FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.id")->fetchAll();
    }
    return $cache;
}

function status_label(string $s): string {
    return ['pending' => 'Order placed', 'paid' => 'Confirmed', 'shipped' => 'Shipped',
            'delivered' => 'Delivered', 'cancelled' => 'Cancelled'][$s] ?? ucfirst($s);
}
function status_badge(string $s): string {
    return '<span class="badge st-' . e($s) . '">' . e(status_label($s)) . '</span>';
}

function product_card(array $p, string $root = ''): void {
    $stock = (int)$p['stock']; $out = $stock <= 0;
    $url = $root . 'product.php?id=' . (int)$p['id']; ?>
    <article class="product">
      <a href="<?= $url ?>" class="thumb">
        <img src="<?= $root ?>assets/images/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <?php if ($out): ?><span class="tag tag-out">Sold out</span>
        <?php elseif ($stock <= 5): ?><span class="tag tag-low">Only <?= $stock ?> left</span>
        <?php elseif (!empty($p['featured'])): ?><span class="tag">Popular</span><?php endif; ?>
      </a>
      <div class="info">
        <small class="cat"><?= e($p['category_name'] ?? '') ?></small>
        <h3><a href="<?= $url ?>"><?= e($p['name']) ?></a></h3>
        <p class="desc"><?= e($p['description']) ?></p>
        <div class="buy-row">
          <strong class="price"><?= peso($p['price']) ?></strong>
          <form method="post" action="<?= $root ?>cart.php" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back" value="<?= e(basename($_SERVER['SCRIPT_NAME']) . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
            <button type="submit" class="btn sm" <?= $out ? 'disabled' : '' ?>><?= icon('cart', 16) ?> Add</button>
          </form>
        </div>
      </div>
    </article>
<?php }
