<?php
// Emergency tool: clears all failed-login locks (for example if the owner is locked out). Command line only:
//   C:\xampp\php\php.exe scripts\unlock.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('SKIP_GATES', true);
require __DIR__ . '/../config.php';
$n = db()->exec("DELETE FROM login_attempts");
echo "Cleared $n failed log-in record(s). All accounts are unlocked.\n";
