-- মাইগ্রেশন 012 — কর্মীর "কাজ করতে আগ্রহী" একাধিক জেলা (011-এর পরে চালান)
-- একজন কর্মী সর্বোচ্চ ৫টি জেলা বাছতে পারেন, অথবা "সব জেলা" (work_all_districts = 1)। "সব জেলা"-র কর্মী
-- কর্মী-তালিকায় যেকোনো জেলার নাম খুঁজলে আসেন। 011-এর একক জেলার ঘর (work_district_id) এই টেবিলে সরে যায়।

CREATE TABLE IF NOT EXISTS worker_work_districts (
    user_id     INT UNSIGNED NOT NULL,
    district_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, district_id),
    KEY idx_wwd_district (district_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN work_all_districts TINYINT(1) NOT NULL DEFAULT 0 AFTER division_id;

INSERT IGNORE INTO worker_work_districts (user_id, district_id)
    SELECT id, work_district_id FROM users WHERE work_district_id IS NOT NULL;

ALTER TABLE users
    DROP COLUMN work_district_id;
