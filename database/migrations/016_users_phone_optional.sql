-- মাইগ্রেশন 016 — মোবাইল ছাড়া কর্মী (015-এর পরে চালান)
-- পুরনো সাইটের যে কর্মীর মোবাইল নাম্বারে অঙ্ক কম/বেশি ছিল, তিনি তালিকায় নামসহ আসেন, মোবাইল ফাঁকা (NULL) থাকে।
-- phone_raw = পুরনো ডাটায় যেমন লেখা ছিল; অ্যাডমিন "অসম্পূর্ণ তথ্য" পেইজে দেখে সঠিক নাম্বার বসাবেন, তখন phone_raw মুছে যায়।
-- uq_users_role_phone-এ একাধিক NULL চলে।

ALTER TABLE users
    MODIFY COLUMN phone CHAR(11) NULL,
    ADD COLUMN phone_raw VARCHAR(40) NULL AFTER phone;
