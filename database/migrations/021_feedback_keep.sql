-- মাইগ্রেশন 021 — মতামতের ইনবক্স: "রাখা" চিহ্ন (020-এর পরে চালান)
-- is_read আগে থেকেই আছে (001)। is_kept = অ্যাডমিন গুরুত্বপূর্ণ বলে রেখেছেন; তালিকায় আলাদা ট্যাবে দেখা যায়।

ALTER TABLE feedback
    ADD COLUMN is_kept TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;
