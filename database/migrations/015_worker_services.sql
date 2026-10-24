-- মাইগ্রেশন 015 — কর্মীর আরও পেশা (014-এর পরে চালান)
-- users.service_slug = কর্মীর প্রধান পেশা; একই কর্মী আরও সর্বোচ্চ ৪টি পেশায় অভিজ্ঞ হতে পারেন, সেগুলো এই টেবিলে।
-- পেশা-ভিত্তিক কর্মী-তালিকায় কর্মী প্রধান পেশায় ও এই আরও-পেশাগুলোতে, সবখানে আসেন।

CREATE TABLE IF NOT EXISTS worker_services (
    user_id      INT UNSIGNED NOT NULL,
    service_slug VARCHAR(80)  NOT NULL,
    PRIMARY KEY (user_id, service_slug),
    KEY idx_ws_service (service_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
