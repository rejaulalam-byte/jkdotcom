-- মাইগ্রেশন 017 — কর্মী-তালিকার ঘূর্ণি ক্রম ও পেইজ-ভিজিটের গণনা (016-এর পরে চালান)
-- ১) worker_rotation: একজন কর্মী একটা পেশার তালিকায় সর্বশেষ কবে "কল" পেয়েছেন (দর্শক নাম-মোবাইল দিয়ে নাম্বার/ইমেইল দেখেছেন)।
--    তালিকা সাজানো হয় "যে কখনও কল পায়নি বা সবচেয়ে আগে পেয়েছে সে আগে"; কল পেলে কর্মী ওই পেশার তালিকায় নিচে নামেন।
-- ২) worker_visits: কর্মীর ব্যক্তিগত পেইজ দিনে কতবার দেখা হয়েছে (একই দর্শক একদিনে একবার)। অ্যাডমিন রিপোর্টের জন্য।

CREATE TABLE IF NOT EXISTS worker_rotation (
    user_id      INT UNSIGNED NOT NULL,
    service_slug VARCHAR(80)  NOT NULL,
    last_call_at DATETIME     NOT NULL,
    calls        INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, service_slug),
    KEY idx_rotation_service (service_slug, last_call_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_visits (
    user_id    INT UNSIGNED NOT NULL,
    visit_date DATE         NOT NULL,
    visits     INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, visit_date),
    KEY idx_visits_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
