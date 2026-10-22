-- মাইগ্রেশন 009 — নিজের লেখা পেশা (008-এর পরে চালান)
-- পেশা তালিকায় না থাকলে কর্মী নিজের শব্দে লেখেন; তখন service_slug ফাঁকা থাকে আর লেখাটা এখানে জমা হয়।
-- অ্যাডমিন বানান ও সত্যতা যাচাই করে সেবা-তালিকায় যোগ করলে service_slug বসে ও এই ঘর ফাঁকা হয়।

ALTER TABLE users
    ADD COLUMN service_custom VARCHAR(100) NULL AFTER service_slug;
