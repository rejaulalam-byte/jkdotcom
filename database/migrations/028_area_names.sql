-- মাইগ্রেশন 028 — "এলাকার নাম" (027-এর পরে চালান)
-- users.area_name = সদস্যের এলাকার ছোট নাম (যেমন আগারগাঁও, উত্তরা-৫নং সেক্টর); কর্মী-তালিকার "এলাকা" কলামে শুধু এটাই দেখায়।
-- area_names_extra = শুরুর তালিকার (database/seeds/areas.php) বাইরের নাম: সদস্য নিজে লিখলে 'pending' (অ্যাডমিন যাচাই করবেন),
-- অ্যাডমিন অনুমোদন বা নিজে যোগ করলে 'approved' এবং তখন সবার সাজেশনে আসে।

ALTER TABLE users
    ADD COLUMN area_name VARCHAR(100) NULL AFTER address;

CREATE TABLE IF NOT EXISTS area_names_extra (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    upazila_id  INT UNSIGNED NULL,
    district_id INT UNSIGNED NULL,
    status      ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_area_extra_status (status),
    KEY idx_area_extra_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
