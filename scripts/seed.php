<?php
// Creates tables and seed data. Safe to run repeatedly. CLI only.
// Allowed from the command line, or from a browser on the same computer (localhost) only.
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');
define('SKIP_GATES', true);   // the setup script must run before the upgrade/maintenance checks
require __DIR__ . '/../config.php';

// XAMPP: create the database if it doesn't exist yet (Docker creates it for you)
try { $pdo = db(); }
catch (PDOException $ex) {
    $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = db();
}

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  is_staff TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
// Upgrade older databases: add the owner column if it is missing.
if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_owner'")->fetch()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin");
    echo "Added the owner role to the users table.\n";
}
if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'is_staff'")->fetch()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER is_owner");
    echo "Added the staff role to the users table.\n";
}
$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  details TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(100) NOT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  address VARCHAR(255) NOT NULL,
  contact VARCHAR(30) NOT NULL,
  payment_method ENUM('cod') NOT NULL DEFAULT 'cod',
  notes VARCHAR(255) NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','paid','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  refund_reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  qty INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB");

// ---- upgrades for older databases (safe to repeat) ----
if (!$pdo->query("SHOW COLUMNS FROM products LIKE 'sale_price'")->fetch()) {
    $pdo->exec("ALTER TABLE products ADD COLUMN sale_price DECIMAL(10,2) NULL AFTER price");
    echo "Added sale prices to products.\n";
}
$col = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'")->fetch();
if ($col && !str_contains((string)$col['Type'], 'refunded')) {
    $pdo->exec("ALTER TABLE orders MODIFY status ENUM('pending','paid','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending'");
    echo "Added the Refunded order status.\n";
}
if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'refund_reason'")->fetch()) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN refund_reason VARCHAR(255) NULL AFTER status");
}
$pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  username VARCHAR(30) NOT NULL DEFAULT '',
  role VARCHAR(10) NOT NULL DEFAULT '',
  action VARCHAR(40) NOT NULL,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  KEY idx_action (action)
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ident VARCHAR(120) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ident (ident, created_at),
  KEY idx_ip (ip, created_at)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(50) PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB");

// ---- categories ----
$cats = [
  'packs-footwear'  => 'Packs & Footwear',
  'tents-sleeping'  => 'Tents & Sleeping',
  'cooking'         => 'Cooking',
  'lighting'        => 'Lighting',
  'camp-essentials' => 'Camp Essentials',
];
$ins = $pdo->prepare("INSERT IGNORE INTO categories (slug, name) VALUES (?, ?)");
foreach ($cats as $slug => $name) $ins->execute([$slug, $name]);

// ---- products (only when table is empty) ----
if (!(int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn()) {
    $catId = $pdo->query("SELECT slug, id FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
    // [category, name, description, details, price, stock, image, featured]
    $products = [
      ['packs-footwear','Hiking Backpack','Durable 50L backpack','Roomy 50L pack with multiple compartments and adjustable straps for multi-day trips.',2500,15,'camping_backpack.jpg',1],
      ['tents-sleeping','Camping Tent','2-person waterproof tent','Two-person dome tent with a waterproof fly and mesh door for ventilation.',3500,10,'camping_tent.jpg',1],
      ['tents-sleeping','Sleeping Bag','All-weather sleeping bag','Mummy-style sleeping bag with a hood, built for cool nights outdoors.',1800,20,'sleeping_bag.jpg',1],
      ['camp-essentials','Camping Knife','Jungle knife','Folding knife with a black-coated blade and a grippy handle.',500,30,'camping_knife.jpg',0],
      ['cooking','Portable Butane Stove','Compact portable butane stove','Compact folding stove for quick meals at camp. Butane canister sold separately.',550,25,'portable_butane.jpg',0],
      ['packs-footwear','Hiking Boots','Waterproof hiking boots','Mid-cut hiking boots with a grippy outsole for rough trails.',2350,12,'boots.jpg',1],
      ['cooking','Portable Cookware','Nesting camp cookware set','Nesting pots, pan and kettle that pack into a mesh carry bag.',650,18,'portable_cookware.jpg',0],
      ['camp-essentials','Camping Chair','Folding camping chair','Foldable chair with armrests and a cup holder, packs into a carry bag.',350,40,'campingg_chair.jpg',0],
      ['camp-essentials','Water Bottle','350ML collapsible water bottle','Collapsible silicone bottle with a carabiner. Folds flat when empty.',300,50,'water_bottle.jpg',0],
      ['lighting','Camping Lantern','Retro-style camp lantern','Retro-style lantern that lights up your whole campsite.',650,14,'camping_lantern.jpg',0],
      ['lighting','Headlamp','Bright hands-free headlamp','Lightweight headlamp with an adjustable strap, for night hikes and camp chores.',500,35,'headlamps.jpg',1],
      ['camp-essentials','Binoculars','Wide-view binoculars','Full-size binoculars for birdwatching, stargazing and scouting trails.',1500,8,'binoculars.jpg',0],
    ];
    $ins = $pdo->prepare("INSERT INTO products (category_id, name, description, details, price, stock, image, featured) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($products as $p) { $p[0] = $catId[$p[0]]; $ins->execute($p); }
    echo "Seeded products.\n";
}

// ---- users (only when table is empty) ----
// No default passwords are stored in this file. Set ADMIN_PASS yourself,
// or a random one is generated and shown ONCE below. Save it!
if (!(int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()) {
    $u = $pdo->prepare("INSERT INTO users (username, name, email, password_hash, is_admin, is_owner) VALUES (?,?,?,?,?,?)");
    $adminUser = env('ADMIN_USER', 'admin');
    $adminPass = env('ADMIN_PASS', '') ?: bin2hex(random_bytes(6));
    $u->execute([$adminUser, 'Owner', env('ADMIN_EMAIL', 'admin@example.com'),
                 password_hash($adminPass, PASSWORD_DEFAULT), 1, 1]);
    echo "Created owner account '" . $adminUser . "'.\n";
    if (env('ADMIN_PASS', '') === '') {
        echo "Admin password (shown only once, save it now): " . $adminPass . "\n";
    }
    // Optional demo customer. Off by default. Enable with SEED_DEMO_USER=1
    if (env('SEED_DEMO_USER', '0') === '1') {
        $demoPass = bin2hex(random_bytes(6));
        $u->execute(['demo', 'Demo Customer', 'customer@example.com', password_hash($demoPass, PASSWORD_DEFAULT), 0, 0]);
        echo "Created demo customer 'demo'. Password (shown only once): " . $demoPass . "\n";
    }
}
// Older databases have no owner yet: the first admin becomes the owner.
if (!(int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_owner = 1")->fetchColumn()) {
    $first = $pdo->query("SELECT id, username FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1")->fetch();
    if ($first) {
        $pdo->prepare("UPDATE users SET is_owner = 1 WHERE id = ?")->execute([$first['id']]);
        echo "No owner found, so '" . $first['username'] . "' is now the Owner.\n";
    }
}
$pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([(string)SCHEMA_VERSION]);
echo "Setup complete.\n";
