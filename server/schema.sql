-- قاعدة بيانات برنامج الخطوات (MySQL / MariaDB)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone         VARCHAR(10)  NOT NULL UNIQUE,
  steps         TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- الخطوات في البطاقة الحالية
  total_visits  INT UNSIGNED NOT NULL DEFAULT 0,      -- كل الزيارات المدفوعة
  free_cups     INT UNSIGNED NOT NULL DEFAULT 0,      -- الأكواب المجانية اللي استلمها
  last_step_at  DATETIME NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username         VARCHAR(50)  NOT NULL UNIQUE,
  display_name     VARCHAR(100) NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  role             ENUM('owner','cashier') NOT NULL DEFAULT 'cashier',
  active           TINYINT(1)   NOT NULL DEFAULT 1,
  session_version  INT UNSIGNED NOT NULL DEFAULT 1,   -- يزيد عند تغيير كلمة المرور أو الإيقاف، فتنطلع كل الأجهزة
  last_login_at    DATETIME NULL,
  created_at       DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- سجل كل حركة: مين أضاف الخطوة ومتى، ومين صرف الكوب
CREATE TABLE IF NOT EXISTS activity (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT UNSIGNED NOT NULL,
  staff_id     INT UNSIGNED NOT NULL,
  kind         ENUM('step','redeem') NOT NULL,
  created_at   DATETIME NOT NULL,
  INDEX (created_at), INDEX (customer_id),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_id)    REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- محاولات الدخول والبحث، لمنع التخمين. تنمسح تلقائيًا بعد يوم
CREATE TABLE IF NOT EXISTS throttle (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket      VARCHAR(64) NOT NULL,
  created_at  DATETIME NOT NULL,
  INDEX (bucket, created_at),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
