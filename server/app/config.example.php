<?php
// انسخ هذا الملف باسم config.php (في نفس المجلد) وعدّل البيانات.
// config.php ما ينرفع على GitHub (موجود في .gitignore) لأنه فيه كلمة مرور قاعدة البيانات.
return [
    // اتصال قاعدة البيانات (من لوحة الاستضافة: اسم القاعدة، المستخدم، كلمة المرور)
    'db_dsn'  => 'mysql:host=localhost;dbname=stepbystep_loyalty;charset=utf8mb4',
    'db_user' => 'DB_USER',
    'db_pass' => 'DB_PASSWORD',

    // مفتاح الإعداد لأول مرة: اكتب كلمة طويلة عشوائية (١٢ حرف أو أكثر)
    // وافتح: setup.php?key=نفس_الكلمة
    // بعد إنشاء حساب المالك رجّعها فاضية '' واحذف setup.php
    'setup_key' => '',

    // يحوّل أي زيارة http إلى https. فعّل SSL في الاستضافة قبل الرفع
    'force_https' => true,

    // إذا الموقع خلف Cloudflare اكتب 'HTTP_CF_CONNECTING_IP' عشان يعرف IP الزائر الحقيقي
    // إذا ما تعرف خلّه null
    'client_ip_header' => null,

    // عدد الخطوات المدفوعة قبل الكوب المجاني (5 = السادس مجاني)
    'goal' => 5,

    // أقل مدة بالثواني بين خطوتين لنفس العميل (يمنع الضغط مرتين بالغلط)
    'step_cooldown' => 30,

    // اسم الكوفي كما يظهر في الصفحات
    'shop_name_en' => 'step by step',
    'shop_name_ar' => 'ستب باي ستب',

    'timezone' => 'Asia/Riyadh',
];
