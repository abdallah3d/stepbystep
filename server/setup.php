<?php
// إعداد أول مرة: ينشئ حساب المالك.
// يحتاج مفتاح الإعداد من config.php، ويقفل نفسه نهائيًا بعد أول حساب. احذفه بعد الإعداد.
require __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store');

if ((int)db()->query('SELECT COUNT(*) FROM staff')->fetchColumn() > 0) {
    http_response_code(404);
    exit('Not found');
}

$key = (string)($CONFIG['setup_key'] ?? '');
if (strlen($key) < 12) {
    http_response_code(403);
    page_head('الإعداد مقفول');
    echo '<section class="card"><h1>الإعداد مقفول</h1>'
       . '<p class="sub">اكتب مفتاح إعداد طويل (١٢ حرف أو أكثر) في app/config.php داخل setup_key، '
       . 'ثم افتح هذي الصفحة بالرابط: setup.php?key=المفتاح</p></section>';
    page_foot();
    exit;
}

$ipKey = throttle_key('setup-ip', client_ip());
if (throttle_count($ipKey, 900) >= 10) {
    http_response_code(429);
    exit('محاولات كثيرة.');
}
if (!hash_equals($key, (string)($_GET['key'] ?? ''))) {
    throttle_hit($ipKey);
    http_response_code(404);
    exit('Not found');
}

$error = null; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim((string)($_POST['username'] ?? ''));
    $n = trim((string)($_POST['name'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $u)) $error = 'اسم المستخدم بالإنجليزي، من ٣ أحرف على الأقل.';
    elseif ($n === '' || strlen($n) > 200) $error = 'اكتب الاسم الظاهر.';
    elseif (strlen($p) < 10) $error = 'كلمة المرور ١٠ أحرف على الأقل.';
    else {
        db()->prepare("INSERT INTO staff (username, display_name, password_hash, role, created_at) VALUES (?, ?, ?, 'owner', ?)")
            ->execute([$u, $n, password_hash($p, PASSWORD_DEFAULT), now()]);
        $done = true;
    }
}

page_head('إعداد البرنامج');
?>
<section class="card">
  <?php if ($done): ?>
    <h1>تم إنشاء حساب المالك</h1>
    <p>الحين رجّع <b>setup_key</b> فاضي في config.php، واحذف ملف <b>setup.php</b> من الاستضافة، ثم ادخل من <a href="cashier/login.php">صفحة الموظفين</a>.</p>
  <?php else: ?>
    <div><h1>إعداد أول مرة</h1><p class="sub">أنشئ حساب المالك. منه تضيف الكاشيرية وتتابع السجل.</p></div>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <div><label for="username">اسم المستخدم (إنجليزي)</label><input id="username" name="username" autocapitalize="none" required></div>
      <div><label for="name">الاسم الظاهر</label><input id="name" name="name" class="ar-in" required></div>
      <div><label for="password">كلمة المرور (١٠ أحرف أو أكثر)</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required></div>
      <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
      <button class="btn big" type="submit">إنشاء الحساب</button>
    </form>
  <?php endif; ?>
</section>
<?php page_foot();
