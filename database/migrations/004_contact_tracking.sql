-- মাইগ্রেশন 004 — কে লুকানো ফোন নাম্বার / ইমেইল দেখছে (003-এর পরে চালান)
-- এখন কাজের তালিকায় (/today) ব্যবহার হয়, পরে কর্মীর তালিকায় (target_type = 'worker')।

-- লুকানো নাম্বার বা ইমেইলে প্রতিটি ক্লিক (অ্যাডমিনের রিপোর্টে একটা "কল" হিসেবে গোনা হয়)
CREATE TABLE IF NOT EXISTS contact_clicks (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('job','worker') NOT NULL,
    target_id   INT UNSIGNED NOT NULL,
    kind        ENUM('phone','email') NOT NULL,
    clicked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash     CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_clicks_target (target_type, target_id, clicked_at),
    KEY idx_clicks_day (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- যোগাযোগ দেখার আগে দর্শক "আমি আগ্রহী" বলে নিজের নাম + মোবাইল রেখে গেছেন
CREATE TABLE IF NOT EXISTS contact_leads (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type   ENUM('job','worker') NOT NULL,
    target_id     INT UNSIGNED NOT NULL,
    kind          ENUM('phone','email') NOT NULL,
    visitor_name  VARCHAR(100) NOT NULL,
    visitor_phone VARCHAR(20)  NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash       CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_leads_target (target_type, target_id, created_at),
    KEY idx_leads_day (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
