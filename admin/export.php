<?php
require '../config.php';
require_login('../');
require_owner();
require '../includes/range.php';

/** Stops spreadsheet programs from running text such as =CMD(...) that a customer typed into a form. */
function csv_cell($v) {
    if ($v === null) return '';
    if (is_int($v) || is_float($v)) return $v;
    $v = (string)$v;
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}

$type = $_GET['type'] ?? '';
[$from, $to] = report_range();
$pdo = db();
if ($type === 'orders') {
    $st = $pdo->prepare("SELECT o.id, o.created_at, o.status, u.username, o.full_name, o.contact, o.address, o.subtotal, o.shipping_fee, o.total, o.notes, o.refund_reason
                         FROM orders o JOIN users u ON u.id = o.user_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.id");
    $st->execute([$from, $to]);
    $head = ['Order', 'Placed', 'Status', 'Username', 'Name', 'Contact', 'Address', 'Subtotal', 'Shipping', 'Total', 'Notes', 'Refund / void reason'];
    $name = "orders_{$from}_to_{$to}";
} elseif ($type === 'items') {
    $st = $pdo->prepare("SELECT o.id, o.created_at, o.status, i.name, i.qty, i.price, i.qty * i.price AS line
                         FROM order_items i JOIN orders o ON o.id = i.order_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.id, i.id");
    $st->execute([$from, $to]);
    $head = ['Order', 'Placed', 'Status', 'Product', 'Qty', 'Price each', 'Line total'];
    $name = "order-items_{$from}_to_{$to}";
} elseif ($type === 'products') {
    $st = $pdo->query("SELECT p.id, p.name, c.name AS category, p.price, p.sale_price, p.stock FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.id");
    $head = ['ID', 'Product', 'Category', 'Price', 'Sale price', 'Stock'];
    $name = 'products_' . date('Y-m-d');
} else {
    http_response_code(400); exit('Unknown export.');
}
$rows = $st->fetchAll(PDO::FETCH_NUM);
log_activity('export_csv', ucfirst($type) . ' CSV downloaded (' . count($rows) . ' rows' . ($type === 'products' ? '' : ", $from to $to") . ')');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // so Excel shows ₱ and accents correctly
fputcsv($out, $head);
$numeric = ['orders' => [7, 8, 9], 'items' => [4, 5, 6], 'products' => [3, 4, 5]][$type];
foreach ($rows as $r) {
    foreach ($r as $i => $v) $r[$i] = (in_array($i, $numeric, true) && is_numeric($v)) ? $v + 0 : csv_cell($v);
    fputcsv($out, $r);
}
fclose($out);
