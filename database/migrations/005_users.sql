-- মাইগ্রেশন 005 — পেশাজীবী কর্মী ও প্রতিনিধির অ্যাকাউন্ট (004-এর পরে চালান)
-- একটা টেবিলেই দুই ধরনের সদস্য; role দিয়ে আলাদা করা হয়। অ্যাডমিন অনুমোদন না করা পর্যন্ত (status = 'pending')
-- সদস্য লগইন করতে পারেন না এবং কোথাও প্রকাশিত হন না। লগইন আইডি = মোবাইল নাম্বার (১১ সংখ্যা)।

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role                 ENUM('worker','agent') NOT NULL,
    status               ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
    name                 VARCHAR(100) NOT NULL,
    phone                CHAR(11)     NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    -- পুরনো সাইট থেকে আনা কর্মীর জন্য সাধারণ প্রাথমিক পাসওয়ার্ড: প্রথম লগইনে বদলাতে বাধ্য করার চিহ্ন
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    -- কর্মীর পেশা = services.slug (slug কখনও বদলায় না); প্রতিনিধির ক্ষেত্রে NULL
    service_slug         VARCHAR(80)  NULL,
    experience_years     TINYINT UNSIGNED NULL,
    bio                  TEXT NULL,
    -- প্রতিনিধির জাতীয় পরিচয়পত্র নাম্বার (১০ বা ১৩ বা ১৭ সংখ্যা); কর্মীর ক্ষেত্রে NULL
    nid_number           VARCHAR(17)  NULL,
    address              VARCHAR(250) NOT NULL,
    upazila_id           INT UNSIGNED NOT NULL,
    district_id          INT UNSIGNED NOT NULL,
    division_id          INT UNSIGNED NOT NULL,
    -- ছবির ফাইলের নাম (৩২ অক্ষরের এলোমেলো নাম + এক্সটেনশন); ফাইল ওয়েব-রুটের বাইরে storage/uploads/photos-এ
    photo                VARCHAR(60)  NULL,
    ip_hash              CHAR(64)     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at          DATETIME     NULL,
    last_login_at        DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_phone (phone),
    KEY idx_users_listing (role, status, service_slug, district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
