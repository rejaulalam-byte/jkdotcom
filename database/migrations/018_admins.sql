-- মাইগ্রেশন 018 — অ্যাডমিন প্যানেলের ব্যবহারকারী ও কার্যলগ (017-এর পরে চালান)
-- admins: role 'super' (সুপার অ্যাডমিন, সব অনুমতি) বা 'admin' (অ্যাডমিনিস্ট্রেটর, permissions-এ দেওয়া অনুমতি)।
-- token_hash + token_expires: ইমেইলের এককালীন লিংক (নতুন অ্যাডমিনের আমন্ত্রণ ও পাসওয়ার্ড ভুলে যাওয়া); লিংকের আসল টোকেন রাখা হয় না, শুধু তার হ্যাশ।
-- password_hash খালি (NULL) = আমন্ত্রণ গেছে কিন্তু এখনও পাসওয়ার্ড বসানো হয়নি, লগইন চলে না।
-- admin_log: কে কখন কী করল (অ্যাডমিন মুছলে লগের নাম থাকে, তাই admin_name আলাদা রাখা)।

CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NULL,
    role          ENUM('super','admin') NOT NULL DEFAULT 'admin',
    permissions   TEXT NULL,
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    token_hash    CHAR(64) NULL,
    token_expires DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_email (email),
    KEY idx_admins_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id   INT UNSIGNED NULL,
    admin_name VARCHAR(100) NOT NULL,
    action     VARCHAR(60)  NOT NULL,
    detail     VARCHAR(500) NULL,
    ip_hash    CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_adminlog_time (created_at),
    KEY idx_adminlog_admin (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
