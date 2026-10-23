-- মাইগ্রেশন 010 — কর্মীর কাজের মূল্যায়ন (রেটিং) (009-এর পরে চালান)
-- সেবা-গ্রহীতা কর্মীর ব্যক্তিগত পেইজে ১ থেকে ৫ (আধা-ধাপে) রেটিং দেন। একই ব্রাউজার-সেশন একই কর্মীকে একটাই
-- রেটিং দিতে পারে (voter_hash = সেশনের এলোমেলো পরিচয়ের হ্যাশ); আবার দিলে আগেরটা বদলে যায়।
-- গড় (AVG) তালিকা-পেইজের "রেটিং" কলামে ও ব্যক্তিগত পেইজের "বর্তমান রেটিং"-এ দেখায়।

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS worker_ratings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    worker_id   INT UNSIGNED NOT NULL,
    rating      DECIMAL(2,1) NOT NULL,
    voter_hash  CHAR(64)     NOT NULL,
    ip_hash     CHAR(64)     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rating_voter (worker_id, voter_hash),
    KEY idx_rating_worker (worker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
