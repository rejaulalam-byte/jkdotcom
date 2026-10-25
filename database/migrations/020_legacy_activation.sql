-- মাইগ্রেশন 020 — পুরনো সাইট থেকে আনা কর্মীর সক্রিয় করার ধাপ (019-এর পরে চালান)
-- phone_called_at: অ্যাডমিন যখন "ফোন করা হয়েছে" টিক দেন। activation_deadline: তার ১৫ দিন পর পর্যন্ত কমন পাসওয়ার্ডে লগইন চলে;
-- তারপর টিক নিজে নিজে বাতিল (তারিখ থেকে হিসাব, আলাদা সময়-ভিত্তিক কাজ নেই)। NULL = এখনও ফোন করা হয়নি।
-- legacy_common_password: পুরনো কর্মীদের জন্য একটাই প্রাথমিক পাসওয়ার্ড (শুধু হ্যাশ); অ্যাডমিন বসান ও বদলান। সারি একটাই (id = 1)।

ALTER TABLE users
    ADD COLUMN phone_called_at DATETIME NULL,
    ADD COLUMN activation_deadline DATETIME NULL;

CREATE TABLE IF NOT EXISTS legacy_common_password (
    id            TINYINT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    updated_by    VARCHAR(100) NOT NULL DEFAULT '',
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
