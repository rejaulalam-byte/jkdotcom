-- মাইগ্রেশন 013 — মোবাইল ও ইমেইল "প্রতি পাশে একবার" (007-এর পরে চালান)
-- একই মানুষ একই ইমেইল ও মোবাইলে একবার পেশাজীবী কর্মী ও একবার প্রতিনিধি হতে পারবে; একই পাশে দ্বিতীয়বার নয়।
-- লগইন, "পাসওয়ার্ড মনে নেই" ও লক এখন পাশ (role) ধরে চলে।

ALTER TABLE users
    DROP INDEX uq_users_phone,
    DROP INDEX uq_users_email,
    ADD UNIQUE KEY uq_users_role_phone (role, phone),
    ADD UNIQUE KEY uq_users_role_email (role, email);
