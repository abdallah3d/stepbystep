<?php
declare(strict_types=1);

// الإعدادات في config.php، وهو ما ينرفع على GitHub لأنه فيه كلمة مرور قاعدة البيانات
if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('الإعدادات ناقصة: انسخ الملف app/config.example.php باسم app/config.php وعدّل بيانات قاعدة البيانات.');
}
$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($CONFIG['timezone']);

// ---- الأخطاء: ما تظهر تفاصيلها للزوار أبدًا، تنسجل في سجل الاستضافة بس ----
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $ex): void {
    error_log('[stepbystep] ' . $ex);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>خطأ مؤقت</title>'
       . '<p>صار خطأ مؤقت. حاول مرة ثانية بعد شوي.</p></html>';
    exit;
});

// كلمة مرور وهمية نقارن فيها لما يكون اسم المستخدم غير موجود، عشان سرعة الرد ما تكشف الأسماء
const DUMMY_HASH = '$2y$10$bDYTWmTWf8Jrwz5a/k.vhOS9b0dB1SDX267UxpJ360sev2HH9Lpvm';

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

if (PHP_SAPI !== 'cli') {
    // ---- تحويل أي اتصال غير مشفّر إلى https ----
    if (!empty($CONFIG['force_https']) && !is_https()) {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && preg_match('/^[a-z0-9.-]+(:\d+)?$/i', $host)) {
            header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
            exit;
        }
        http_response_code(403);
        exit('الاتصال لازم يكون آمن (https).');
    }

    // ---- هيدرات الحماية ----
    header('X-Frame-Options: DENY');                       // ما أحد يقدر يحط الصفحة داخل موقعه
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');                // روابط الصفحات ما تنتقل لمواقع ثانية
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; "
         . "font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; form-action 'self'; "
         . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    if (is_https()) header('Strict-Transport-Security: max-age=15552000');

    // ---- الجلسة ----
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('sbs_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $pdo = new PDO($CONFIG['db_dsn'], $CONFIG['db_user'], $CONFIG['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function go(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** يحوّل الأرقام العربية والفارسية ويشيل الرموز، ويقبل 9665xxxxxxxx أو 5xxxxxxxx */
function normalize_phone(string $raw): ?string
{
    $raw = strtr($raw, [
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
    ]);
    $d = preg_replace('/\D/', '', $raw);
    if (str_starts_with($d, '00966')) $d = '0' . substr($d, 5);
    elseif (str_starts_with($d, '966')) $d = '0' . substr($d, 3);
    elseif (preg_match('/^5\d{8}$/', $d)) $d = '0' . $d;
    return preg_match('/^05\d{8}$/', $d) ? $d : null;
}

/** 0551234567 ← 05••••4567 */
function mask_phone(string $p): string
{
    return substr($p, 0, 2) . '••••' . substr($p, -4);
}

function ar_num(int|string $n): string
{
    return strtr((string)$n, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(400);
        exit('انتهت صلاحية الصفحة، حدّثها وحاول مرة ثانية.');
    }
}

function client_ip(): string
{
    global $CONFIG;
    $h = $CONFIG['client_ip_header'] ?? null;
    if ($h && !empty($_SERVER[$h])) {
        $ip = trim(explode(',', (string)$_SERVER[$h])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

// ---- حد المحاولات: محفوظ في قاعدة البيانات، فما ينفع تجاوزه بمسح الكوكيز ----
// القيم تنحفظ مشفّرة (ما نخزن IP ولا اسم مستخدم كنص)، وتنمسح بعد يوم
function throttle_key(string $kind, string $value): string
{
    global $CONFIG;
    return $kind . ':' . substr(hash_hmac('sha256', strtolower($value), 'sbs|' . $CONFIG['db_pass']), 0, 40);
}

function throttle_count(string $key, int $window): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM throttle WHERE bucket = ? AND created_at > ?');
    $st->execute([$key, date('Y-m-d H:i:s', time() - $window)]);
    return (int)$st->fetchColumn();
}

function throttle_hit(string $key): void
{
    db()->prepare('INSERT INTO throttle (bucket, created_at) VALUES (?, ?)')->execute([$key, now()]);
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM throttle WHERE created_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function throttle_clear(string $key): void
{
    db()->prepare('DELETE FROM throttle WHERE bucket = ?')->execute([$key]);
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** يرسم خانات الخطوات المضلّعة */
function render_steps(int $steps, int $goal): string
{
    $poly = '30,4 70,4 96,30 96,70 70,96 30,96 4,70 4,30';
    $h = '<div class="steps">';
    for ($i = 1; $i <= $goal; $i++) {
        $on = $i <= $steps;
        $glyph = $on ? '✦' : ar_num($i);
        $h .= '<div class="step' . ($on ? ' on' : '') . '"><svg viewBox="0 0 100 100" aria-hidden="true">'
            . "<polygon class=\"fill\" points=\"$poly\"/><polygon class=\"oct\" points=\"$poly\"/>"
            . "<text class=\"glyph\" x=\"50\" y=\"58\" text-anchor=\"middle\">$glyph</text></svg>"
            . '<span>خطوة ' . ar_num($i) . '</span></div>';
    }
    $ready = $steps >= $goal;
    $h .= '<div class="step free' . ($ready ? ' ready' : '') . '"><svg viewBox="0 0 100 100" aria-hidden="true">'
        . "<polygon class=\"fill\" points=\"$poly\"/><polygon class=\"oct\" points=\"$poly\"/>"
        . '<text class="glyph" x="50" y="58" text-anchor="middle">مجاني</text></svg>'
        . '<span>الكوب ' . ar_num($goal + 1) . '</span></div>';
    return $h . '</div>';
}

function page_head(string $title): void
{
    global $CONFIG;
    $base = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/cashier/') ? '../' : '';
    echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
       . '<meta name="robots" content="noindex">'
       . '<title>' . e($title) . '</title>'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@500;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600&family=Quicksand:wght@500;700&display=swap">'
       . '<link rel="stylesheet" href="' . $base . 'assets/style.css?v=3"></head><body><div class="wrap">'
       . '<header><svg class="mark" viewBox="0 0 100 100" aria-hidden="true"><path d="M70 22 L60 12 H32 L18 26 V40 L30 52 H58 L72 64 V76 L60 88 H30 L18 76"/></svg>'
       . '<div class="brand"><span class="en">' . e($CONFIG['shop_name_en']) . '</span><span class="ar">برنامج الخطوات</span></div></header>';
}

function page_foot(): void
{
    echo '</div></body></html>';
}
