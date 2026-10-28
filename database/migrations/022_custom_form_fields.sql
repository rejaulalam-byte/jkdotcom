-- মাইগ্রেশন 022 — ফর্মে অ্যাডমিনের যোগ করা নতুন ঘর (021-এর পরে চালান)
-- form_custom_fields: কোন ফর্মে (worker | agent | post) কোন ঘর, তার নাম, ধরন, ড্রপডাউনের বিকল্প, আবশ্যক কি না, ক্রম।
-- form_custom_values: দর্শকের পূরণ করা মান; record_id = users.id (worker/agent) অথবা job_posts.id (post)।
-- ঘর মুছলে তার সব মানও মুছে যায় (ON DELETE CASCADE)।

CREATE TABLE form_custom_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_key VARCHAR(20) NOT NULL,
    label VARCHAR(200) NOT NULL,
    field_type VARCHAR(20) NOT NULL,
    options TEXT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_form_custom_fields_form (form_key, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE form_custom_values (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_id INT UNSIGNED NOT NULL,
    form_key VARCHAR(20) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    value TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_form_custom_values_record (form_key, record_id),
    CONSTRAINT fk_form_custom_values_field FOREIGN KEY (field_id) REFERENCES form_custom_fields (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
