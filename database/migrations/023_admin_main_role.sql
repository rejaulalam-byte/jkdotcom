-- অ্যাডমিনের তিন ভূমিকা: 'super' (সব), 'main' (মেইন অ্যাডমিন / ওনার: কাঠামো ছাড়া সব, একজনই) ও 'admin' (সাব-অ্যাডমিন: permissions-এ দেওয়া কাজ)।
ALTER TABLE admins MODIFY role ENUM('super','main','admin') NOT NULL DEFAULT 'admin';
