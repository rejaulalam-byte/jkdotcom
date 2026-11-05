-- মাইগ্রেশন 027 — সাইটের পেইজ-ভিজিট (026-এর পরে চালান)
-- একজন দর্শক (ব্রাউজার-সেশনের এলোমেলো কোড, IP বা ব্যক্তিগত তথ্য নয়) একদিনে কোন কোন পেইজ দেখেছেন; প্রতি (দিন, দর্শক, পেইজ) একটা সারি।
-- অ্যাডমিন ড্যাশবোর্ডের "আজকের দর্শক" ঘরের জন্য: শুধু হোম, একাধিক পেইজ, শুধু সেবা-পেইজ, কতগুলো সেবার পেইজ খোলা হয়েছে।

CREATE TABLE IF NOT EXISTS page_visits (
    visit_date DATE         NOT NULL,
    visitor    CHAR(16)     NOT NULL,
    page       VARCHAR(120) NOT NULL,
    PRIMARY KEY (visit_date, visitor, page),
    KEY idx_page_visits_page (visit_date, page)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
