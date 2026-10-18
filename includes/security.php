<?php
// Activity log, login lockout and "confirm your password" helpers.
// Loaded by config.php. Every function here is safe to call even before the
// database has been upgraded (scripts/seed.php): logging never breaks a page.

const LOGIN_MAX_FAILS = 5;     // wrong passwords allowed for one username/email ...
const LOGIN_WINDOW_MIN = 15;   // ... within this many minutes, then the account is locked this long
const IP_MAX_FAILS = 20;       // wrong passwords allowed from one computer in the same window
const SUDO_SECONDS = 300;      // how long a password confirmation stays valid (5 minutes)

// ---------------------------------------------------------------- activity log

/** Writes one line to the activity log (owner-only page). Never throws. */
function log_activity(string $action, string $detail = '', ?array $actor = null): void {
    try {
        $actor = $actor ?? current_user();
        $role = $actor ? ($actor['role'] ?? (function_exists('current_role') ? current_role() : '')) : '';
        db()->prepare("INSERT INTO activity_log (user_id, username, role, action, detail, ip) VALUES (?,?,?,?,?,?)")
            ->execute([$actor['id'] ?? null, mb_substr((string)($actor['username'] ?? ''), 0, 30), mb_substr($role, 0, 10),
                       mb_substr($action, 0, 40), mb_substr($detail, 0, 500), mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    } catch (Throwable $e) {
        error_log('activity log: ' . $e->getMessage());
    }
}

/** Describes what changed between two rows, e.g. "price: 500 → 600; stock: 5 → 9". */
function diff_text(array $old, array $new, array $labels): string {
    $out = [];
    foreach ($labels as $k => $label) {
        $a = (string)($old[$k] ?? ''); $b = (string)($new[$k] ?? '');
        if (is_numeric($a) && is_numeric($b)) { if ((float)$a === (float)$b) continue; }
        elseif ($a === $b) continue;
        $out[] = $label . ': ' . ($a === '' ? '(none)' : mb_substr($a, 0, 40)) . ' → ' . ($b === '' ? '(none)' : mb_substr($b, 0, 40));
    }
    return implode('; ', $out);
}

// ---------------------------------------------------------------- login lockout

function login_ident(string $s): string { return mb_substr(mb_strtolower(trim($s)), 0, 120); }
function client_ip(): string { return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45); }

/** Minutes left on a lock for this username/email (or this computer), 0 when not locked. */
function login_lock_minutes(string $ident): int {
    try {
        $best = 0;
        foreach ([['ident', $ident, LOGIN_MAX_FAILS], ['ip', client_ip(), IP_MAX_FAILS]] as [$col, $val, $max]) {
            $st = db()->prepare("SELECT COUNT(*) AS n, UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(MAX(created_at)) AS ago
                                 FROM login_attempts WHERE $col = ? AND created_at > NOW() - INTERVAL " . LOGIN_WINDOW_MIN . " MINUTE");
            $st->execute([$val]);
            $r = $st->fetch();
            if ($r && (int)$r['n'] >= $max) $best = max($best, (int)ceil((LOGIN_WINDOW_MIN * 60 - (int)$r['ago']) / 60));
        }
        return max(0, $best);
    } catch (Throwable $e) { return 0; }
}
function login_record_failure(string $ident): void {
    try {
        db()->prepare("INSERT INTO login_attempts (ident, ip) VALUES (?, ?)")->execute([$ident, client_ip()]);
        db()->exec("DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY");
    } catch (Throwable $e) { error_log('login attempts: ' . $e->getMessage()); }
}
function login_clear_failures(string ...$idents): void {
    try {
        $st = db()->prepare("DELETE FROM login_attempts WHERE ident = ?");
        foreach ($idents as $i) $st->execute([login_ident($i)]);
    } catch (Throwable $e) {}
}
/** Usernames/emails (lower-case) that are locked right now. */
function locked_idents(): array {
    try {
        return db()->query("SELECT ident FROM login_attempts WHERE created_at > NOW() - INTERVAL " . LOGIN_WINDOW_MIN . " MINUTE
                            GROUP BY ident HAVING COUNT(*) >= " . LOGIN_MAX_FAILS)->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { return []; }
}

// ---------------------------------------------------------------- confirm password (for dangerous actions)

function sudo_active(): bool { return (int)($_SESSION['sudo_until'] ?? 0) > time(); }

/** Dangerous actions call this first. If the owner has not confirmed their password in the last 5 minutes,
 *  they are sent to the confirmation page and then back to $back, where they repeat the action. */
function require_sudo(string $back): void {
    if (sudo_active()) return;
    flash('For safety, confirm your password first, then repeat the action.', 'err');
    redirect('confirm.php?next=' . urlencode($back));
}
