# হোস্টিং গাইড — আপলোড, আপডেট ও সমস্যা-সমাধান (jorurikaj.com)

রেজার জন্য ধাপে ধাপে নোট: সাইট হোস্টিংয়ে তোলা, আপডেট করা, আর হোস্টিং কোম্পানি সার্ভার বদলালে কী করতে হয়।
**নিয়ম:** পাসওয়ার্ড কখনো চ্যাটে বা গিটে লেখা যাবে না। cPanel-এ লগইন রেজা নিজে করেন।

## ক. সাইট কীভাবে সাজানো (মনে রাখার জন্য)

- কোড থাকে `~/repositories/jkdotcom` ফোল্ডারে (cPanel Git Version Control এটা GitHub থেকে টানে)।
- সাব-ডোমেইন `new.jorurikaj.com`-এর Document Root = `~/repositories/jkdotcom/public`। শুধু `public` ইন্টারনেটে খোলা।
- গোপন সেটিং থাকে `~/repositories/jkdotcom/.env` ফাইলে (গিটে যায় না)।
- ডাটাবেস: `jorurika_jkn`, ইউজার: `jorurika_jkn`। অ্যাডমিন প্যানেলের পথ `.env`-এর `ADMIN_PATH`।
- লগ: `storage/logs/app-YYYY-MM.log` ও `php-error.log` (সমস্যা হলে প্রথমে এখানে দেখুন)।

## খ. নতুন সাইট প্রথমবার তোলা

1. cPanel → **Domains/Subdomains**: সাব-ডোমেইন বানান, Document Root দিন `repositories/jkdotcom/public`।
2. cPanel → **Git Version Control** → Create: Clone URL `https://github.com/rejaulalam-byte/jkdotcom.git`, Repository Path `repositories/jkdotcom`।
3. cPanel → **MySQL Databases**: ডাটাবেস বানান; ইউজার বানান; ইউজারকে ডাটাবেসে যোগ করে **ALL PRIVILEGES** দিন।
4. File Manager-এ `.env.example` কপি করে `.env` বানান। DB_NAME, DB_USER, DB_PASS, APP_URL, APP_KEY, MAIL_*, ADMIN_PATH, ADMIN_SETUP_KEY বসান।
5. phpMyAdmin → ডাটাবেস বাছুন → Import: `database/migrations/` এর `001` থেকে শেষ পর্যন্ত ক্রমানুসারে চালান।
6. Git Version Control → Manage → **Deploy HEAD Commit** (`.cpanel.yml` লেখার ফোল্ডার বানায় ও অনুমতি ঠিক করে)।
7. সাইট খুলে দেখুন; অ্যাডমিন প্যানেলে `/ADMIN_PATH/setup` একবার চালিয়ে প্রথম সুপার অ্যাডমিন বানান।

## গ. রোজকার আপডেট (কোড বদলের পর)

1. কোড `main` ব্রাঞ্চে GitHub-এ push হয়েছে কি না দেখুন।
2. cPanel → Git Version Control → Manage → **Pull or Deploy** → **Update from Remote**।
3. তারপর **Deploy HEAD Commit**।
4. নতুন মাইগ্রেশন থাকলে phpMyAdmin-এ Import করুন (ক্রমানুসারে, একবারই)।
5. সাইট খুলে যাচাই করুন; ভুল থাকলে `storage/logs` দেখুন।

## ঘ. হোস্টিং কোম্পানি সার্ভার বদলালে (২৬ অক্টোবর ২০২৬-এর ঘটনা)

**লক্ষণ:** অ্যাডমিনে পাসওয়ার্ড "ভুল" বলে, ইমেইলে কোড আসে না, কিন্তু সাইট খোলে।
**কারণ:** ফাইল ও ডাটাবেস এসেছে, কিন্তু **ডাটাবেস ইউজার** আসেনি। লগে লেখা থাকে `Access denied for user ...`। ডাটাবেসে না ঢুকলে সাইট "লোকাল প্রিভিউ" মোডে চলে যায়: আসল অ্যাডমিন দেখে না, কোড মেইলে না গিয়ে ফাইলে লেখা হয়।

**সমাধানের ধাপ:**
1. File Manager → `storage/logs/app-YYYY-MM.log` খুলে শেষ লাইন দেখুন।
2. `Access denied` থাকলে: cPanel → **MySQL Databases** → ইউজার আছে কি না দেখুন।
3. না থাকলে ইউজার বানান (একই নাম, পাসওয়ার্ড: আগেরটাই দিলে `.env` বদলাতে হয় না; নতুন দিলে `.env`-এর `DB_PASS` বদলান)।
4. ইউজারকে ডাটাবেসে যোগ করে **ALL PRIVILEGES** দিন।
5. phpMyAdmin-এ দেখুন টেবিলগুলো আছে কি না (`admins`, `users` ইত্যাদি)।
6. সাইটে একটা ডাটাবেস-নির্ভর পেজ খুলুন (যেমন `/services/electrician`), তারপর লগে নতুন ভুল আসছে কি না দেখুন।
7. **Git Version Control:** ফোল্ডারের পথ বদলে থাকতে পারে (`/home4` → `/home2`)। তালিকায় পুরোনো পথ দেখালে ওই ফোল্ডারেই নতুন করে রিপো যোগ করুন (Create, একই Repository Path)। ফাইল মোছে না।
8. `.env` ফাইলটা আছে কি না ও ঠিক আছে কি না দেখুন।

## ঙ. অন্যান্য সাধারণ সমস্যা

| লক্ষণ | কী দেখবেন |
|---|---|
| সাদা পাতা / ৫০০ ভুল | `storage/logs/php-error.log` |
| ছবি আপলোড হয় না | `storage` ও `public/uploads`-এ লেখার অনুমতি; Deploy HEAD Commit আবার চালান |
| মেইল যায় না | `.env`-এর MAIL_FROM; cPanel → Email Deliverability; spam ফোল্ডার |
| লগইনে "বেশি চেষ্টা" | অ্যাডমিন → লক পেজ থেকে খুলুন, বা `storage/ratelimit` খালি করুন |
| "Update from Remote" ব্যর্থ | রিপোর পথ ঠিক আছে কি না (ঘ-৭); GitHub-এ push হয়েছে কি না |
| পুরোনো পেজ দেখাচ্ছে | ব্রাউজারে Ctrl+F5; Deploy HEAD Commit হয়েছে কি না |

## চ. আটকে গেলে

- লগের শেষ কয়েকটা লাইন কপি করে Claude-কে দিন। Claude cPanel-এ ঢুকে দেখতে পারে (লগইন আপনি করে দিলে)।
- পাসওয়ার্ড চ্যাটে লিখবেন না। কাজ শেষে প্রয়োজনে পাসওয়ার্ড বদলে নিন।
- বড় কিছু বদলানোর আগে File Manager থেকে `.env` ও ডাটাবেসের ব্যাকআপ (phpMyAdmin → Export) নিয়ে রাখুন।
