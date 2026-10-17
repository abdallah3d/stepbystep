<?php
// سجل الحركات — للمالك فقط: مين أضاف خطوة لمين ومتى، وتنبيه على الحالات الغريبة
require __DIR__ . '/auth.php';
$staff = require_staff('owner');
$pdo = db();

const ALERT_STEPS_PER_DAY = 3;   // عميل أخذ ٣ خطوات أو أكثر في يوم واحد يستاهل نظرة

$day = (string)($_GET['d'] ?? date('Y-m-d'));
$ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? strtotime($day) : false;
if ($ts === false) { $ts = strtotime(date('Y-m-d')); }
$day  = date('Y-m-d', $ts);
$from = $day . ' 00:00:00';
$to   = date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00';
$prev = date('Y-m-d', strtotime($day . ' -1 day'));
$next = date('Y-m-d', strtotime($day . ' +1 day'));
$isToday = $day === date('Y-m-d');

$byStaff = $pdo->prepare("SELECT s.display_name,
        SUM(CASE WHEN a.kind = 'step' THEN 1 ELSE 0 END) AS steps,
        SUM(CASE WHEN a.kind = 'redeem' THEN 1 ELSE 0 END) AS redeems
    FROM activity a JOIN staff s ON s.id = a.staff_id
    WHERE a.created_at >= ? AND a.created_at < ?
    GROUP BY s.id, s.display_name ORDER BY steps DESC");
$byStaff->execute([$from, $to]);
$byStaff = $byStaff->fetchAll();

$alerts = $pdo->prepare("SELECT c.phone, COUNT(*) AS n
    FROM activity a JOIN customers c ON c.id = a.customer_id
    WHERE a.kind = 'step' AND a.created_at >= ? AND a.created_at < ?
    GROUP BY c.id, c.phone HAVING COUNT(*) >= " . (int)ALERT_STEPS_PER_DAY . " ORDER BY n DESC");
$alerts->execute([$from, $to]);
$alerts = $alerts->fetchAll();

$rows = $pdo->prepare('SELECT a.kind, a.created_at, s.display_name, c.phone
    FROM activity a JOIN staff s ON s.id = a.staff_id JOIN customers c ON c.id = a.customer_id
    WHERE a.created_at >= ? AND a.created_at < ?
    ORDER BY a.created_at DESC, a.id DESC LIMIT 300');
$rows->execute([$from, $to]);
$rows = $rows->fetchAll();

page_head('السجل · ' . $CONFIG['shop_name_en']);
staff_topbar($staff, 'activity.php');
?>
<section class="card">
  <div class="daynav">
    <a class="btn ghost small" href="?d=<?= e($prev) ?>">اليوم السابق</a>
    <h1 class="phone"><?= e($day) ?></h1>
    <?php if (!$isToday): ?><a class="btn ghost small" href="?d=<?= e($next) ?>">اليوم التالي</a><?php else: ?><span class="pill">اليوم</span><?php endif; ?>
  </div>

  <?php if ($alerts): ?>
    <div class="panel alert">
      <h2>تنبيه</h2>
      <p class="sub">عملاء أخذوا <?= ar_num(ALERT_STEPS_PER_DAY) ?> خطوات أو أكثر في نفس اليوم. تأكد إن الطلبات حقيقية.</p>
      <?php foreach ($alerts as $a): ?>
        <div class="who"><span class="phone"><?= e(mask_phone($a['phone'])) ?></span><span class="pill warn"><?= ar_num((int)$a['n']) ?> خطوات</span></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div>
    <h2>حسب الموظف</h2>
    <div class="list">
      <?php foreach ($byStaff as $b): ?>
        <div class="item"><span><?= e($b['display_name']) ?></span><span class="sub"><?= ar_num((int)$b['steps']) ?> خطوة · <?= ar_num((int)$b['redeems']) ?> كوب مجاني</span></div>
      <?php endforeach; if (!$byStaff): ?><p class="sub">ما فيه حركات في هذا اليوم.</p><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($rows): ?>
<section class="card">
  <h2>كل الحركات</h2>
  <div class="log">
    <?php foreach ($rows as $r): ?>
      <div class="log-row">
        <span class="time"><?= e(substr($r['created_at'], 11, 5)) ?></span>
        <span><?= e($r['display_name']) ?> <span class="pill<?= $r['kind'] === 'redeem' ? ' ready' : '' ?>"><?= $r['kind'] === 'redeem' ? 'كوب مجاني' : 'خطوة' ?></span></span>
        <span class="phone"><?= e(mask_phone($r['phone'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php page_foot();
