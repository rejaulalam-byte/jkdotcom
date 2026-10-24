-- মাইগ্রেশন 014 — পুরনো সাইটের কর্মী আনার প্রস্তুতি (013-এর পরে চালান)
-- ১) legacy_id: পুরনো `worker_info.id`; একই কর্মী দুবার আমদানি ঠেকায়। নতুন নিবন্ধনে NULL।
-- ২) পুরনো তালিকার ঢাকার "দনিয়া" এলাকা নতুন তালিকায় ছিল না।

ALTER TABLE users
    ADD COLUMN legacy_id VARCHAR(20) NULL AFTER id,
    ADD UNIQUE KEY uq_users_legacy_id (legacy_id);

INSERT IGNORE INTO upazilas (id, district_id, name_bn, name_en) VALUES
    (512, 47, 'দনিয়া', 'Dania');
