<?php
// صفحة العميل — قراءة فقط، ما فيها أي إضافة أو تعديل
require __DIR__ . '/app/bootstrap.php';
$goal = (int)$CONFIG['goal'];

const LOOKUP_WINDOW = 600;   // ١٠ دقايق
const LOOKUP_MAX    = 40;    // بحث لكل IP، يكفي زبائن الكوفي ويوقف اللي يجرب أرقام كثيرة

$customer = null; $error = null; $phone = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ipKey = throttle_key('lookup-ip', client_ip());
    if (throttle_count($ipKey, LOOKUP_WINDOW) >= LOOKUP_MAX) {
        $error = 'محاولات كثيرة، جرّب بعد شوي.';
    } else {
        throttle_hit($ipKey);
        $phone = normalize_phone((string)($_POST['phone'] ?? ''));
        if ($phone === null) {
            $error = 'اكتب رقم جوالك كامل، يبدأ بـ 05 ومن عشرة أرقام.';
        } else {
            $st = db()->prepare('SELECT steps, total_visits, free_cups FROM customers WHERE phone = ?');
            $st->execute([$phone]);
            $customer = $st->fetch() ?: false;
        }
    }
}

page_head('خطواتي · ' . $CONFIG['shop_name_en']);
?>
<section class="card">
  <div>
    <h1>خطواتك عندنا</h1>
    <p class="sub">كل زيارة خطوة. <?= ar_num($goal) ?> خطوات، والكوب <?= $goal + 1 === 6 ? 'السادس' : ar_num($goal + 1) ?> علينا.</p>
  </div>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label for="phone">رقم جوالك</label>
    <div class="row">
      <input id="phone" name="phone" inputmode="numeric" placeholder="05xxxxxxxx" autocomplete="tel" enterkeyhint="search" value="<?= e($phone) ?>" required>
      <button class="btn" type="submit">اعرض بطاقتي</button>
    </div>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  </form>

  <?php if ($customer === false): ?>
    <p class="status">ما عندك خطوات للحين. قل رقمك للكاشير مع طلبك القادم.</p>
  <?php elseif ($customer): $s = (int)$customer['steps']; $left = $goal - $s; ?>
    <?= render_steps($s, $goal) ?>
    <p class="status">
      <?php if ($s >= $goal): ?>كوبك القادم مجاني ☕ اطلبه الحين
      <?php elseif ($s === 0): ?>بطاقة جديدة، أول خطوة مع طلبك القادم
      <?php elseif ($left === 1): ?>باقي خطوة وحدة على كوبك المجاني
      <?php else: ?>باقي <?= ar_num($left) ?> خطوات على كوبك المجاني<?php endif; ?>
    </p>
    <div class="stats">
      <div><b><?= ar_num((int)$customer['free_cups']) ?></b><span>أكواب مجانية استلمتها</span></div>
      <div><b><?= ar_num((int)$customer['total_visits']) ?></b><span>إجمالي زياراتك</span></div>
    </div>
  <?php endif; ?>
</section>
<p class="note">رقمك يُستخدم لبرنامج الخطوات فقط</p>
<?php page_foot();
