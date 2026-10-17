<?php
// إدارة الموظفين — للمالك فقط: إضافة كاشير، إيقافه، وتغيير كلمات المرور
require __DIR__ . '/auth.php';
$me = require_staff('owner');
$pdo = db();

const MIN_PASSWORD = 10;

function staff_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM staff WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $u = trim((string)($_POST['username'] ?? ''));
        $n = trim((string)($_POST['name'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        $exists = $pdo->prepare('SELECT 1 FROM staff WHERE username = ?');
        $exists->execute([$u]);
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $u)) flash('اسم المستخدم بالإنجليزي، من ٣ أحرف على الأقل.', 'err');
        elseif ($exists->fetchColumn()) flash('اسم المستخدم مستخدم من قبل.', 'err');
        elseif ($n === '' || strlen($n) > 200) flash('اكتب اسم الموظف.', 'err');
        elseif (strlen($p) < MIN_PASSWORD) flash('كلمة المرور ' . ar_num(MIN_PASSWORD) . ' أحرف على الأقل.', 'err');
        else {
            $pdo->prepare("INSERT INTO staff (username, display_name, password_hash, role, created_at) VALUES (?, ?, ?, 'cashier', ?)")
                ->execute([$u, $n, password_hash($p, PASSWORD_DEFAULT), now()]);
            flash('تمت إضافة ' . $n . '.');
        }
        go('staff.php');
    }

    $target = staff_by_id((int)($_POST['id'] ?? 0));
    if (!$target) { flash('الموظف غير موجود.', 'err'); go('staff.php'); }
    $isMe = (int)$target['id'] === (int)$me['id'];

    if ($action === 'toggle') {
        if ($isMe) {
            flash('ما تقدر توقف حسابك.', 'err');
        } else {
            // الإيقاف يطلّعه من كل أجهزته فورًا
            $pdo->prepare('UPDATE staff SET active = 1 - active, session_version = session_version + 1 WHERE id = ?')->execute([$target['id']]);
            flash((int)$target['active'] ? 'تم إيقاف ' . $target['display_name'] . ' وطلع من كل الأجهزة.' : 'تم تفعيل ' . $target['display_name'] . '.');
        }
    } elseif ($action === 'password') {
        $p = (string)($_POST['password'] ?? '');
        if (strlen($p) < MIN_PASSWORD) {
            flash('كلمة المرور ' . ar_num(MIN_PASSWORD) . ' أحرف على الأقل.', 'err');
        } else {
            $pdo->prepare('UPDATE staff SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')
                ->execute([password_hash($p, PASSWORD_DEFAULT), $target['id']]);
            if ($isMe) {
                // هذا الجهاز يبقى داخل، والأجهزة الثانية تطلع
                $_SESSION['staff_ver'] = (int)staff_by_id((int)$me['id'])['session_version'];
                session_regenerate_id(true);
            }
            flash('تم تغيير كلمة المرور، وطلعت الأجهزة الثانية من الحساب.');
        }
    }
    go('staff.php');
}

$list = $pdo->query("SELECT id, username, display_name, role, active, last_login_at FROM staff ORDER BY role = 'owner' DESC, active DESC, display_name")->fetchAll();

page_head('الموظفين · ' . $CONFIG['shop_name_en']);
staff_topbar($me, 'staff.php');
?>
<section class="card">
  <h1>الموظفين</h1>
  <?php foreach ($list as $s): $self = (int)$s['id'] === (int)$me['id']; ?>
    <div class="panel staff">
      <div class="who">
        <span><b><?= e($s['display_name']) ?></b> <span class="sub phone"><?= e($s['username']) ?></span></span>
        <span class="inline">
          <span class="pill<?= $s['role'] === 'owner' ? ' ready' : '' ?>"><?= $s['role'] === 'owner' ? 'مالك' : 'كاشير' ?></span>
          <?php if (!(int)$s['active']): ?><span class="pill warn">موقوف</span><?php endif; ?>
        </span>
      </div>
      <p class="sub">آخر دخول: <?= $s['last_login_at'] ? '<span class="phone">' . e(substr($s['last_login_at'], 0, 16)) . '</span>' : 'ما دخل للحين' ?></p>
      <?php if (!$self): ?>
        <div class="inline">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn ghost small" type="submit"><?= (int)$s['active'] ? 'إيقاف الحساب' : 'تفعيل الحساب' ?></button></form>
        </div>
      <?php endif; ?>
      <details>
        <summary><?= $self ? 'تغيير كلمة مروري' : 'تغيير كلمة المرور' ?></summary>
        <form method="post" class="row pw">
          <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <input name="password" type="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" placeholder="كلمة مرور جديدة" aria-label="كلمة مرور جديدة" required>
          <button class="btn small" type="submit">حفظ</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
</section>

<section class="card">
  <div><h2>إضافة كاشير</h2><p class="sub">كل موظف له حسابه، عشان السجل يوضح مين سوّى كل خطوة.</p></div>
  <form method="post" class="stack">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div><label for="nu">اسم المستخدم (إنجليزي)</label><input id="nu" name="username" autocapitalize="none" autocomplete="off" required></div>
    <div><label for="nn">اسم الموظف</label><input id="nn" name="name" class="ar-in" required></div>
    <div><label for="np">كلمة المرور (<?= ar_num(MIN_PASSWORD) ?> أحرف أو أكثر)</label><input id="np" name="password" type="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD ?>" required></div>
    <button class="btn big" type="submit">إضافة</button>
  </form>
</section>
<?php page_foot();
