<?php
/** Date range for reports/exports from ?from=YYYY-MM-DD&to=YYYY-MM-DD (default: last 30 days; ?range=all for everything). */
function report_range(): array {
    $valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    $today = date('Y-m-d');
    if (($_GET['range'] ?? '') === 'all') return ['2000-01-01', '2100-01-01', 'All time', 'all'];
    $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
    $key = $_GET['range'] ?? '';
    if ($key === 'today') { $from = $to = $today; }
    elseif ($key === '7') { $from = date('Y-m-d', strtotime('-6 days')); $to = $today; }
    elseif ($key === 'month') { $from = date('Y-m-01'); $to = $today; }
    elseif (!$valid($from) || !$valid($to)) { $from = date('Y-m-d', strtotime('-29 days')); $to = $today; $key = '30'; }
    if ($from > $to) [$from, $to] = [$to, $from];
    return [$from, $to, $from === $to ? date('M j, Y', strtotime($from)) : date('M j, Y', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to)), $key];
}
