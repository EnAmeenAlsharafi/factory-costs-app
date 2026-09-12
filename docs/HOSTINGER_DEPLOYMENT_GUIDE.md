# دليل نشر تطبيق إدارة تكاليف المصنع على استضافة Hostinger المشتركة
# Hostinger Shared Hosting Deployment Guide

يوفر هذا الدليل خطوات مفصلة وعملية لنشر تطبيق **إدارة تكاليف المصنع (Laravel / PHP / MySQL)** على خطط الاستضافة المشتركة من **Hostinger** عبر لوحة التحكم **hPanel**.

---

## 1. إنشاء قاعدة بيانات MariaDB / MySQL في hPanel

1. سجّل الدخول إلى لوحة التحكم **Hostinger hPanel**.
2. توجّه إلى قسم **Databases (قواعد البيانات)** ثم اختر **Management (إدارة قواعد البيانات)**.
3. تحت خيار **Create a New MySQL Database and Database User**:
   - **Database Name**: أدخل اسماً مناسباً، مثلاً: `factory_costs`.
   - **Username**: أدخل اسم المستخدم، مثلاً: `factory_user`.
   - **Password**: أدخل كلمة مرور قوية جداً واحتفظ بها.
4. انقر على **Create (إنشاء)**.
5. لاحظ اسم قاعدة البيانات واسم المستخدم الكاملين اللذين يولدهما Hostinger (مثل: `u123456789_factory` و `u123456789_user`).

---

## 2. رفع ملفات التطبيق إلى الاستضافة

### الطريقة الموصى بها (عبر لوحة التحكم أو SSH):

1. قم بضغط مجلد المشروع بالكامل في ملف ZIP (تأكد من شمول مجلد `vendor` ومجلد `public` وكافة الملفات البرمجية).
2. في **hPanel**، افتح **File Manager (مدير الملفات)**.
3. انتقل إلى المجلد الرئيسي لحسابك: `/home/u123456789/` (خارج مجلد `public_html`).
4. أنشئ مجلداً جديداً باسم: `factory-costs-app`.
5. ارفع ملف ZIP داخل مجلد `factory-costs-app` ثم قم بفك الضغط (Extract).

> [!CAUTION]
> **تحذير أمني هام جداً**:
> لا ترفع ملفات Laravel الأساسية (مثل `.env`، `app/`، `config/`، `storage/`، `routes/`) داخل مجلد `public_html` مباشرة، حتى لا تكون أسرار التطبيق وقواعد البيانات ومفاتيح التشفير متاحة للتحميل للعامة عبر المتصفح.

---

## 3. توجيه النطاق (Document Root) إلى مجلد `public`

### الخيار أ (إذا كانت خطة الاستضافة تتيح تغيير Document Root):
1. في **hPanel**، توجّه إلى **Websites** ثم اختر النطاق المستهدف.
2. ادخل على **Advanced** -> **Directory** أو إعدادات النطاق.
3. عيّن مسار الجذر ليكون:
   `/home/u123456789/factory-costs-app/public`
4. احفظ الإعدادات.

---

### الخيار ب (إذا لم تدعم الاستضافة تغيير مسار Document Root الافتراضي):
إذا كان الخادم يُلزمك باستخدام مجلد `public_html` كمسار رئيسي للموقع، اتبع هذه البنية الآمنة:

1. ارفع ملفات مشروع Laravel داخل مجلد خاص:
   `/home/u123456789/factory-costs-app/`
2. انقل **محتويات** مجلد `factory-costs-app/public/*` فقط إلى داخل:
   `/home/u123456789/public_html/`
   (بحيث تصبح ملفات `index.php`، `.htaccess`، ومجلدي `css/` و `js/` داخل `public_html`).
3. افتح ملف `/home/u123456789/public_html/index.php` وعدّل السطرين التاليين:
   ```php
   // السطر القديم:
   // require __DIR__.'/../vendor/autoload.php';
   // $app = require_once __DIR__.'/../bootstrap/app.php';

   // التعديل الآمن الجديد:
   require __DIR__.'/../factory-costs-app/vendor/autoload.php';
   $app = require_once __DIR__.'/../factory-costs-app/bootstrap/app.php';
   ```
4. أنشئ ملف `.htaccess` داخل `public_html` لضمان حظر أي ملفات حساسة وإعادة التوجيه إلى `index.php`:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteRule ^ index.php [L]
   </IfModule>
   ```

---

## 4. إعداد ملف البيئة `.env` للإنتاج

في مجلد التطبيق (`factory-costs-app`)، أنشئ ملف `.env` (بالنسخ من `.env.example`):

```ini
APP_NAME="إدارة تكاليف المصنع"
APP_ENV=production
APP_KEY=base64:oBw7LhuG6+35MlT5yg5+gVJQGeFjiinRs6WzFmBoNRc=
APP_DEBUG=false
APP_TIMEZONE=Asia/Riyadh
APP_URL=https://your-factory-domain.com

APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
APP_FAKER_LOCALE=ar_SA

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# بيانات قاعدة بيانات Hostinger MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_factory
DB_USERNAME=u123456789_user
DB_PASSWORD=YourPasswordHere

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

---

## 5. ضبط تصاريح المجلدات (Permissions)

من خلال SSH في Hostinger أو عبر مدير الملفات، اضبط التصاريح بحيث يمتلك خادم الويب صلاحية القراءة والكتابة للمجلدات المؤقتة:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## 6. تشغيل التهجير وبذر البيانات (Migrations & Seeders)

عبر الاتصال بـ **SSH** في Hostinger:

```bash
cd /home/u123456789/factory-costs-app

# تشغيل التهجير
php artisan migrate --force

# بذر البيانات المرجعية الأساسية وحساب المشرف
php artisan db:seed --force
```

> **ملاحظة**: إذا لم يكن SSH متاحاً في باقتك، يمكنك تشغيل المهاجرات محلياً وتصدير ملف SQL لقاعدة البيانات عبر phpMyAdmin واستيراده في phpMyAdmin على Hostinger.

---

## 7. إنشاء رابط التخزين (Storage Link)

```bash
php artisan storage:link
```

---

## 8. التخزين المؤقت للإنتاج (Optimization & Caching)

للحصول على أعلى أداء ممكن وسرعة استجابة فائقة على الاستضافة المشتركة، شغّل أوامر التخزين المؤقت:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 9. بيانات حساب المسؤول الافتراضي

تم إنشاء حساب المشرف مسبقاً عبر المزوّد (`UserSeeder`):
- **البريد الإلكتروني**: `admin@factory.com`
- **كلمة المرور الافتراضية**: `AdminPassword@2026!`

> [!IMPORTANT]
> يرجى تغيير كلمة المرور فور تسجيل الدخول لأول مرة.

---

## 10. إعداد HTTPS وشهادة SSL

1. في لوحة **hPanel**، انتقل إلى **Security** -> **SSL**.
2. فعّل شهادة **Let's Encrypt SSL المجانية** على النطاق.
3. فعّل خيار **Force HTTPS** لفرض التشفير الآمن لكافة الاتصالات.

---

## 11. النسخ الاحتياطي والاستعادة (Backup & Restore)

### النسخ الاحتياطي التلقائي والدوري:
1. من **hPanel** -> قسم **Files** -> **Backups**.
2. يتيح Hostinger إنشاء وتنزيل نسخة احتياطية يومية أو أسبوعية لقاعدة البيانات والملفات.

### التصدير اليدوي لقاعدة البيانات:
1. افتح **phpMyAdmin** من لوحة **hPanel**.
2. اختر قاعدة بيانات التطبيق (`u123456789_factory`).
3. انقر على تبويب **Export (تصدير)** ثم اختر **Quick** و **SQL**.
4. انقر **Export** لحفظ نسخة احتياطية محلية.
