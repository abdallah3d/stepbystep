<?php
require __DIR__ . '/auth.php';
$staff = require_staff();
$goal = (int)$CONFIG['goal'];
$pdo = db();

function customer_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function customer_by_phone(string $phone): ?array
{
    $st = db()->prepare('SELECT * FROM customers WHERE phone = ?');
    $st->execute([$phone]);
    return $st->fetch() ?: null;
}

function log_action(int $customerId, int $staffId, string $kind): void
{
    db()->prepare('INSERT INTO activity (customer_id, staff_id, kind, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$customerId, $staffId, $kind, now()]);
}

// ---- الإجراءات: كلها POST ثم تحويل، فتحديث الصفحة ما يكرر شيء، ورقم الجوال ما يظهر في الرابط ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'find') {
        $phone = normalize_phone((string)($_POST['phone'] ?? ''));
        if ($phone === null) { flash('الرقم لازم يبدأ بـ 05 ويكون عشرة أرقام.', 'err'); go('index.php'); }
        $c = customer_by_phone($phone);
        if ($c) go('index.php?c=' . $c['id']);
        $_SESSION['pending_phone'] = $phone;
        go('index.php?new=1');
    }

    if ($action === 'create') {
        // الرقم يجي من الجلسة اللي بحث فيها الكاشير، مو من المتصفح
        $phone = $_SESSION['pending_phone'] ?? null;
        unset($_SESSION['pending_phone']);
        if (!$phone) go('index.php');
        if (!customer_by_phone($phone)) {
            try {
                $pdo->prepare('INSERT INTO customers (phone, created_at, updated_at) VALUES (?, ?, ?)')->execute([$phone, now(), now()]);
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') throw $ex;   // انضاف من جهاز ثاني بنفس اللحظة
            }
        }
        $c = customer_by_phone($phone);
        $action = 'add';
    } else {
        $c = customer_by_id((int)($_POST['c'] ?? 0));
    }

    if (!$c) { flash('العميل غير موجود.', 'err'); go('index.php'); }

    if ($action === 'add') {
        // كل الشروط داخل نفس الأمر، فما ينفع جهازين يتجاوزون الحد بنفس اللحظة
        $tooSoon = date('Y-m-d H:i:s', time() - (int)$CONFIG['step_cooldown']);
        $st = $pdo->prepare('UPDATE customers SET steps = steps + 1, total_visits = total_visits + 1, last_step_at = ?, updated_at = ?
                             WHERE id = ? AND steps < ? AND (last_step_at IS NULL OR last_step_at <= ?)');
        $st->execute([now(), now(), $c['id'], $goal, $tooSoon]);
        if ($st->rowCount() === 1) {
            log_action((int)$c['id'], (int)$staff['id'], 'step');
            $new = (int)$c['steps'] + 1;
            flash($new >= $goal ? 'اكتملت الخطوات! الطلب الجاي مجاني.' : 'تمت إضافة الخطوة ' . ar_num($new) . '.');
        } else {
            $fresh = customer_by_id((int)$c['id']);
            flash((int)$fresh['steps'] >= $goal
                ? 'البطاقة مكتملة. اصرف الكوب المجاني أول.'
                : 'انضافت خطوة لهذا العميل قبل ثواني. إذا الطلب جديد انتظر شوي وأعد المحاولة.', 'err');
        }
    } elseif ($action === 'redeem') {
        $st = $pdo->prepare('UPDATE customers SET steps = 0, free_cups = free_cups + 1, updated_at = ? WHERE id = ? AND steps >= ?');
        $st->execute([now(), $c['id'], $goal]);
        if ($st->rowCount() === 1) {
            log_action((int)$c['id'], (int)$staff['id'], 'redeem');
            flash('تم صرف الكوب المجاني، وبدأت بطاقة جديدة.');
        } else {
            flash('العميل ما أكمل خطواته للحين.', 'err');
        }
    }
    go('index.php?c=' . $c['id']);
}

// ---- العرض ----
$c = isset($_GET['c']) ? customer_by_id((int)$_GET['c']) : null;
$pending = isset($_GET['new']) ? ($_SESSION['pending_phone'] ?? null) : null;
$confirm = isset($_GET['confirm']) && $c && (int)$c['steps'] >= $goal;

$recent = $pdo->query('SELECT id, phone, steps FROM customers ORDER BY updated_at DESC, id DESC LIMIT 8')->fetchAll();
$today = $pdo->prepare('SELECT kind, COUNT(*) AS n FROM activity WHERE created_at >= ? GROUP BY kind');
$today->execute([date('Y-m-d 00:00:00')]);
$counts = ['step' => 0, 'redeem' => 0];
foreach ($today as $r) $counts[$r['kind']] = (int)$r['n'];

page_head('الكاشير · ' . $CONFIG['shop_name_en']);
staff_topbar($staff, 'index.php');
?>
<section class="card">
  <div><h1>تسجيل زيارة</h1><p class="sub">اكتب رقم العميل وأضف خطوة مع كل طلب.</p></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="find">
    <label for="q">رقم جوال العميل</label>
    <div class="row">
      <input id="q" name="phone" inputmode="numeric" placeholder="05xxxxxxxx" enterkeyhint="search" autocomplete="off" required>
      <button class="btn ghost" type="submit">بحث</button>
    </div>
  </form>

  <?php if ($pending && !$c): ?>
    <div class="panel">
      <div class="who"><span class="phone"><?= e($pending) ?></span><span class="pill">عميل جديد</span></div>
      <p>أول خطوة تسجّله في البرنامج.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
        <button class="btn big" type="submit">سجّل وأضف أول خطوة ✦</button></form>
    </div>
  <?php elseif ($c): $s = (int)$c['steps']; ?>
    <div class="who">
      <span class="phone"><?= e($c['phone']) ?></span>
      <span class="pill<?= $s >= $goal ? ' ready' : '' ?>"><?= $s >= $goal ? 'يستحق كوب مجاني' : ar_num($s) . ' من ' . ar_num($goal) ?></span>
    </div>
    <?= render_steps($s, $goal) ?>
    <p class="sub center">زيارات: <?= ar_num((int)$c['total_visits']) ?> · أكواب مجانية مصروفة: <?= ar_num((int)$c['free_cups']) ?></p>
    <?php if ($s < $goal): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="c" value="<?= (int)$c['id'] ?>">
        <button class="btn big" type="submit">أضف خطوة ✦</button></form>
    <?php elseif (!$confirm): ?>
      <a class="btn big ghost" href="?c=<?= (int)$c['id'] ?>&amp;confirm=1">صرف الكوب المجاني</a>
    <?php else: ?>
      <div class="confirm">
        <p>تأكيد صرف الكوب المجاني وتصفير البطاقة؟</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="redeem"><input type="hidden" name="c" value="<?= (int)$c['id'] ?>">
          <button class="btn" type="submit">نعم، اصرف</button></form>
        <a class="btn ghost" href="?c=<?= (int)$c['id'] ?>">إلغاء</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card">
  <div class="who"><h2>آخر العملاء</h2><span class="sub">اليوم: <?= ar_num($counts['step']) ?> خطوة · <?= ar_num($counts['redeem']) ?> كوب مجاني</span></div>
  <div class="list">
    <?php foreach ($recent as $r): ?>
      <a class="item" href="?c=<?= (int)$r['id'] ?>">
        <span class="phone"><?= e(mask_phone($r['phone'])) ?></span>
        <span class="dots"><?php for ($i = 1; $i <= $goal; $i++): ?><i class="<?= $i <= (int)$r['steps'] ? 'on' : '' ?>"></i><?php endfor; ?></span>
      </a>
    <?php endforeach; if (!$recent): ?><p class="sub">ما فيه عملاء للحين. أول عميل يظهر هنا.</p><?php endif; ?>
  </div>
</section>
<?php page_foot();
