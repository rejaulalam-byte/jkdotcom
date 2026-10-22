-- মাইগ্রেশন 008 — জেন্ডার (007-এর পরে চালান)
-- ফর্মে নামের পরে জেন্ডার বাছা হয়: পুরুষ → "মি:", মহিলা → "মিস", অন্যান্য → শুধু নাম (ধন্যবাদ-বার্তা ও স্বাগত লেখায়)।
-- আগের সারিতে জেন্ডার নেই (NULL): সেখানে শুধু নাম দেখানো হয়।

ALTER TABLE users
    ADD COLUMN gender ENUM('male','female','other') NULL AFTER name;

ALTER TABLE job_posts
    ADD COLUMN contact_gender ENUM('male','female','other') NULL AFTER contact_name;
