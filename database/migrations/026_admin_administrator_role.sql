-- চতুর্থ ভূমিকা 'administrator': মেইন অ্যাডমিনের সমান অনুমতি, কিন্তু যতজন দরকার (মেইন একজনই)। সুপার অ্যাডমিন বানান।
-- সাধারণ ব্যবহার: মেইন অ্যাডমিনের সমস্যা নিজে দেখার জন্য সাময়িক একটা অ্যাকাউন্ট, কাজ শেষে মুছে ফেলা যায়।
ALTER TABLE admins MODIFY role ENUM('super','main','administrator','admin') NOT NULL DEFAULT 'admin';
