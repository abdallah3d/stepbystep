<?php
require __DIR__ . '/auth.php';
header('Cache-Control: no-store');

if (!empty($_SESSION['staff_id'])) go('index.php');

const LOGIN_WINDOW   = 900;   // ربع ساعة
const LOGIN_MAX_IP   = 10;    // محاولات خاطئة من نفس الجهاز/الشبكة
const LOGIN_MAX_USER = 5;     // محاولات خاطئة على نفس الحساب

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');
    $ipKey   = throttle_key('login-ip', client_ip());
    $userKey = throttle_key('login-user', $u);

    if (throttle_count($ipKey, LOGIN_WINDOW) >= LOGIN_MAX_IP || throttle_count($userKey, LOGIN_WINDOW) >= LOGIN_MAX_USER) {
        $error = 'محاولات كثيرة. انتظر ربع ساعة وحاول مرة ثانية.';
    } else {
        $st = db()->prepare('SELECT id, password_hash, session_version FROM staff WHERE username = ? AND active = 1');
        $st->execute([$u]);
        $row = $st->fetch();
        // نتحقق من كلمة مرور دايمًا، حتى لو الاسم غير موجود
        $valid = password_verify($p, $row['password_hash'] ?? DUMMY_HASH);

        if ($row && $valid) {
            throttle_clear($userKey);
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE staff SET password_hash = ? WHERE id = ?')->execute([password_hash($p, PASSWORD_DEFAULT), $row['id']]);
            }
            session_regenerate_id(true);
            $_SESSION = [
                'staff_id'    => (int)$row['id'],
                'staff_ver'   => (int)$row['session_version'],
                'staff_since' => time(),
                'staff_seen'  => time(),
            ];
            db()->prepare('UPDATE staff SET last_login_at = ? WHERE id = ?')->execute([now(), $row['id']]);
            go('index.php');
        }

        throttle_hit($ipKey);
        throttle_hit($userKey);
        usleep(random_int(150000, 350000));
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}

page_head('دخول الموظفين');
?>
<section class="card">
  <div><h1>دخول الموظفين</h1><p class="sub">هذي الصفحة للكاشير والمالك فقط.</p></div>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <div><label for="username">اسم المستخدم</label><input id="username" name="username" autocomplete="username" autocapitalize="none" required></div>
    <div><label for="password">كلمة المرور</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <button class="btn big" type="submit">دخول</button>
  </form>
</section>
<?php page_foot();
