<?php
// يُستدعى في بداية كل صفحة موظفين: أي أحد مو مسجّل دخول يرجع لصفحة الدخول
require dirname(__DIR__) . '/app/bootstrap.php';

const SESSION_IDLE_LIMIT = 8 * 3600;    // خروج تلقائي بعد ٨ ساعات بدون استخدام
const SESSION_MAX_AGE    = 14 * 3600;   // وحد أقصى ١٤ ساعة للجلسة حتى لو مستخدمة

function staff_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** يرجّع الموظف الحالي، أو يحوّله لصفحة الدخول. $role = 'owner' للصفحات الخاصة بالمالك */
function require_staff(?string $role = null): array
{
    header('Cache-Control: no-store');
    $sid = $_SESSION['staff_id'] ?? null;
    $now = time();
    $staff = null;

    if ($sid
        && ($now - ($_SESSION['staff_seen'] ?? 0)) <= SESSION_IDLE_LIMIT
        && ($now - ($_SESSION['staff_since'] ?? 0)) <= SESSION_MAX_AGE) {
        $st = db()->prepare('SELECT id, username, display_name, role, session_version FROM staff WHERE id = ? AND active = 1');
        $st->execute([$sid]);
        $staff = $st->fetch() ?: null;
        // تغيير كلمة المرور أو إيقاف الحساب يطلّع كل الأجهزة اللي كانت داخلة فيه
        if ($staff && (int)$staff['session_version'] !== (int)($_SESSION['staff_ver'] ?? -1)) $staff = null;
    }

    if (!$staff) {
        staff_logout();
        go('login.php');
    }

    if ($role !== null && $staff['role'] !== $role) {
        http_response_code(403);
        page_head('غير مسموح');
        echo '<section class="card"><h1>غير مسموح</h1><p class="sub">هذي الصفحة للمالك فقط.</p>'
           . '<a class="btn ghost" href="index.php">رجوع</a></section>';
        page_foot();
        exit;
    }

    $_SESSION['staff_seen'] = $now;
    return $staff;
}

/** روابط أعلى صفحات الموظفين */
function staff_topbar(array $staff, string $current): void
{
    $links = ['index.php' => 'الكاشير'];
    if ($staff['role'] === 'owner') {
        $links['activity.php'] = 'السجل';
        $links['staff.php'] = 'الموظفين';
    }
    echo '<div class="topbar"><span class="sub">أهلًا ' . e($staff['display_name']) . '</span><nav class="nav">';
    foreach ($links as $href => $label) {
        echo $href === $current
            ? '<span aria-current="page">' . e($label) . '</span>'
            : '<a href="' . $href . '">' . e($label) . '</a>';
    }
    echo '<form method="post" action="logout.php">' . csrf_field() . '<button class="link" type="submit">خروج</button></form>';
    echo '</nav></div>';
    $f = flash();
    if ($f) echo '<p class="flash ' . e($f['type']) . '" role="status">' . e($f['msg']) . '</p>';
}
