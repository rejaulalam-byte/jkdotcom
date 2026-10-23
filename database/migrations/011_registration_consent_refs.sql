-- মাইগ্রেশন 011 — সম্মতি, কাজের জেলা ও রেফারেন্স (010-এর পরে চালান)
-- তিন ফর্মেই (পেশাজীবী, প্রতিনিধি, কাজ প্রকাশ) গোপনীয়তা নীতিমালা ও শর্তাবলিতে সম্মতির টিক বাধ্যতামূলক;
-- কখন সম্মতি দেওয়া হয়েছে (consent_at) প্রমাণ হিসেবে জমা থাকে।
-- পেশাজীবীর ফর্মে নতুন: কোন জেলায় কাজ করতে আগ্রহী (work_district_id) এবং দুইজন রেফারেন্স (লেখা আকারে)।

ALTER TABLE users
    ADD COLUMN work_district_id INT UNSIGNED NULL AFTER division_id,
    ADD COLUMN reference_1 VARCHAR(500) NULL AFTER bio,
    ADD COLUMN reference_2 VARCHAR(500) NULL AFTER reference_1,
    ADD COLUMN consent_at DATETIME NULL AFTER created_at;

ALTER TABLE job_posts
    ADD COLUMN consent_at DATETIME NULL AFTER created_at;
