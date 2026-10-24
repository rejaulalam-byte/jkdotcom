-- মাইগ্রেশন 019 — কর্মী/প্রতিনিধির নতুন অবস্থা: সংরক্ষিত ও ডাস্টবিন (018-এর পরে চালান)
-- 'suspended' = হোল্ড। "মুছুন" চাপলে সদস্য আগে 'reserved' (সংরক্ষিত) হয়: সাইট থেকে সরে যায়, লগইন বন্ধ, সব তথ্য অক্ষত।
-- সংরক্ষিতে ৩০ দিন পর নিজে 'trashed' (ডাস্টবিন); ডাস্টবিনে আরও ৩০ দিন পর চিরতরে মোছা (শুধু অ্যাডমিন প্যানেল খোলা হলে চলে)।
-- previous_status: ফিরিয়ে আনলে আগের অবস্থায় ফেরার জন্য। status_note: হোল্ড/বাতিলের কারণ (ঐচ্ছিক)।
-- status_changed_at: সংরক্ষিত/ডাস্টবিনের ৩০ দিন গোনার সময়।

ALTER TABLE users
    MODIFY COLUMN status ENUM('pending','approved','rejected','suspended','reserved','trashed') NOT NULL DEFAULT 'pending',
    ADD COLUMN status_note VARCHAR(255) NULL,
    ADD COLUMN previous_status VARCHAR(20) NULL,
    ADD COLUMN status_changed_at DATETIME NULL;
