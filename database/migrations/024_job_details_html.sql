-- কাজের বিজ্ঞপ্তির বিস্তারিত অ্যাডমিন পূর্ণ এডিটরে সাজালে এখানে থাকে (ছাঁকা HTML); দর্শকের ফর্মের সাদা লেখা details-এই থাকে।
ALTER TABLE job_posts ADD COLUMN details_html MEDIUMTEXT NULL AFTER details;
