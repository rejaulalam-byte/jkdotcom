-- সেবার পেইজ (/services/<slug>) দিনে কতবার দেখা হয়েছে; একই দর্শক একই সেবায় একদিনে একবার। ড্যাশবোর্ডের গ্রাফের জন্য।
CREATE TABLE IF NOT EXISTS service_visits (
    service_slug VARCHAR(80)  NOT NULL,
    visit_date   DATE         NOT NULL,
    visits       INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (service_slug, visit_date),
    KEY idx_service_visits_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
