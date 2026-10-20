<?php

declare(strict_types=1);

/*
 * Default wording of the website (taken from the Figma design).
 * The admin panel can override any of these keys; overrides are stored in the
 * "content_blocks" table. {Words in braces} are shown in bold.
 * Texts shown with colored() (e.g. home.rep.text) may also use
 * [[color:gold|words]] — allowed colours: gold, amber, white, red (TEXT_COLORS in helpers.php).
 */
return [
    // ---- Site-wide -------------------------------------------------------
    'site.title'          => 'Jorurikaj.com — আপনার প্রয়োজন, আমাদের অগ্রাধিকার',
    'site.description'    => 'JoruriKaj.com-এ খুঁজে নিন আপনার দৈনন্দিন প্রয়োজনের জন্য দক্ষ সেবাদানকারী। বাংলাদেশের একটি সেবামূলক ওয়েবসাইট।',
    'site.brand'          => 'Jorurikaj',
    'site.brand_suffix'   => '.com',
    'site.tagline'        => 'একটি সেবা মুলক ওয়েবসাইট',
    'site.top_tagline_brand' => 'Jorurikaj.com',
    'site.top_tagline'    => ' বাংলাদেশে একটি বৃহৎ ওয়ার্কস্টেশন।',
    'site.phone_top'      => '+880 1761 441 911',
    'site.email'          => 'info@jorurikaj.com',
    'site.phone_footer'   => '(+88) 01761441911',
    'site.hotline'        => '(+88) 01761441911',
    'site.website'        => 'www.jorurikaj.com',
    'site.address_1'      => 'H-100, New Airport Road, Kakoli',
    'site.address_2'      => 'Bonani, Near Kakoli Overbridge',
    'site.address_3'      => 'Dhaka 1213',
    'site.coordinates'    => '23.793577, 90.400596',
    'site.social.facebook'  => '#',
    'site.social.whatsapp'  => '#',
    'site.social.instagram' => '#',
    'site.social.telegram'  => '#',
    'site.social.twitter'   => '#',
    'site.social.linkedin'  => '#',

    'settings.jobs_visible_days' => '3',
    // 0 = show every social icon (empty ones link to "#"); 1 = hide icons that have no address.
    // Owner: keep 0 for now, switch to 1 only when he says so.
    'settings.social_hide_empty' => '0',

    // ---- Home: hero ------------------------------------------------------
    'home.hero.badge'     => 'আপনার প্রয়োজন, আমাদের অগ্রাধিকার',
    'home.hero.title_1'   => 'সঠিক সাহায্য, ঠিক তখনই -',
    'home.hero.title_2'   => 'যখন আপনার প্রয়োজন',
    'home.hero.text'      => 'JoruriKaj.com-এ খুঁজে নিন আপনার দৈনন্দিন প্রয়োজনের জন্য দক্ষ সেবাদানকারী। সঠিক মানুষ খুঁজে পাওয়া, সেবা সম্পর্কে জানা এবং প্রয়োজনীয় সহায়তা নেওয়া—সবকিছু হোক আরও সহজ।',
    'home.hero.button'    => 'আমাদের সেবা সমূহ',
    'home.today.title'    => 'আজকের কাজের তালিকা',
    'home.today.empty'    => 'আজ নতুন কোনো কাজের বিজ্ঞপ্তি নেই।',
    'home.today.note'     => 'কাজ সম্পর্কে জানতে {আজকের প্রকাশিত} মেনুতে ভিজিট করুন',

    // ---- Home: ads + representative bar ----------------------------------
    'home.ads.label'      => 'বিজ্ঞাপন',
    'home.rep.text'       => 'বাংলাদেশ জুড়ে আমাদের নিজস্ব প্রতিনিধি নির্বাচন করা হচ্ছে। আপনি যদি আগ্রহী হন, তাহলে আমাদের সাথে যোগাযোগ করুন',

    // ---- Home: services --------------------------------------------------
    'home.services.badge'  => 'সহজ সেবা, সহজ জীবন।',
    'home.services.title'  => 'আপনার প্রতিটি প্রয়োজনের সমাধান, এক প্ল্যাটফর্মে',
    'home.services.text_1' => 'আপনার প্রতিদিনের প্রয়োজনীয় সেবার জন্য আর খুঁজতে হবে না এখানে-সেখানে। JoruriKaj.com-এ খুঁজে নিন আপনার এলাকার দক্ষ সেবাদানকারীকে,',
    'home.services.text_2' => 'প্রয়োজন অনুযায়ী বেছে নিন সঠিক মানুষটিকে, আর নিশ্চিন্তে এগিয়ে যান আপনার কাজের পথে।',
    'home.services.button' => 'সেবাসমূহ দেখুন',

    // ---- Home: about -----------------------------------------------------
    'home.about.badge'    => 'আমাদের পরিচয়',
    'home.about.title'    => 'কাজের সন্ধান ও সেবার সংযোগ, একই প্ল্যাটফর্মে',
    'home.about.text'     => 'Jorurikaj.com বাংলাদেশের মানুষের দৈনন্দিন প্রয়োজন, জরুরি সেবা এবং পেশাজীবীদের মধ্যে সহজ যোগাযোগের একটি নির্ভরযোগ্য ডিজিটাল প্ল্যাটফর্ম গড়ে তোলার প্রত্যয়ে যাত্রা শুরু করেছে। আমাদের দেশে প্রতিদিন অসংখ্য মানুষ বিভিন্ন ধরনের কাজের জন্য দক্ষ ও অভিজ্ঞ পেশাজীবীদের খোঁজ করেন। অন্যদিকে, অনেক দক্ষ কারিগর ও পেশাজীবী রয়েছেন, যাঁদের কাজের দক্ষতা ও অভিজ্ঞতা থাকা সত্ত্বেও উপযুক্ত কাজের সুযোগ কিংবা সম্ভাব্য গ্রাহকের কাছে পৌঁছানোর সহজ কোনো মাধ্যম নেই। ফলে একদিকে মানুষ প্রয়োজনীয় সেবা পেতে ভোগান্তির শিকার হন, অন্যদিকে অনেক পেশাজীবী তাঁদের যোগ্যতা অনুযায়ী কাজ পাওয়ার সুযোগ থেকে বঞ্চিত হন।',
    'home.about.button'   => 'সেবাসমূহ দেখুন',
    'home.about.map_alt'  => 'বাংলাদেশের মানচিত্র',

    // ---- Home: steps -----------------------------------------------------
    'home.steps.badge'    => 'আপনার জন্য, আপনার প্রয়োজনেই',
    'home.steps.title'    => 'আপনার প্রতিদিনের কাজে সহজ সমাধান',
    'home.steps.text'     => 'JoruriKaj.com-এ খুঁজে নিন আপনার দৈনন্দিন প্রয়োজনীয় বিভিন্ন ধরনের সেবা। বাসা-বাড়ির কাজ, মেরামত, কারিগরি সহায়তা থেকে শুরু করে নানা প্রয়োজনে উপযুক্ত সেবাদানকারী খুঁজে পাওয়া এখন আরও সহজ। আপনার প্রয়োজন অনুযায়ী সেবা বেছে নিন এবং সময় ও পরিশ্রম বাঁচিয়ে এগিয়ে যান।',
    'home.steps.1.title'  => 'সেবা নির্বাচন করুন',
    'home.steps.1.text'   => 'আপনার প্রয়োজনীয় সেবাটি খুঁজে নিন।',
    'home.steps.2.title'  => 'অনুরোধ পাঠান',
    'home.steps.2.text'   => 'সময় ও ঠিকানা দিয়ে রিকোয়েস্ট করুন।',
    'home.steps.3.title'  => 'যাচাইকৃত প্রোভাইডার',
    'home.steps.3.text'   => 'নিকটস্থ দক্ষ সেবাদানকারী যুক্ত হবেন।',
    'home.steps.4.title'  => 'কাজ সম্পন্ন',
    'home.steps.4.text'   => 'সন্তুষ্ট হলে রেটিং দিন।',

    // ---- Home: FAQ + feedback --------------------------------------------
    'home.faq.badge'      => 'আপনার জিজ্ঞাসা',
    'home.faq.title'      => 'আপনার মনে থাকা প্রশ্নগুলোর সহজ উত্তর',
    'home.faq.text'       => 'JoruriKaj.com-এ সেবা খোঁজা কিংবা নিজের দক্ষতা অনুযায়ী কাজের সুযোগ তৈরি করা—উভয় ক্ষেত্রেই আপনার মনে নানা প্রশ্ন আসতে পারে। কীভাবে নিবন্ধন করবেন, সেবা খুঁজবেন বা সম্ভাব্য গ্রাহকের সঙ্গে যোগাযোগ করবেন, তার প্রয়োজনীয় উত্তর এখানে পাবেন।',
    'home.faq.1.q'        => 'JoruriKaj.com কী?',
    'home.faq.1.a'        => 'JoruriKaj.com এমন একটি অনলাইন প্ল্যাটফর্ম, যেখানে মানুষ তাদের প্রয়োজনীয় কাজের জন্য উপযুক্ত সেবাদানকারী খুঁজতে পারেন এবং পেশাজীবীরা নিজেদের দক্ষতা ও পরিচিতি তুলে ধরতে পারেন।',
    'home.faq.2.q'        => 'JoruriKaj.com কারা ব্যবহার করতে পারবেন?',
    'home.faq.2.a'        => 'যাঁরা নিজেদের প্রয়োজনীয় কাজের জন্য দক্ষ ব্যক্তি খুঁজছেন এবং যাঁরা নিজেদের পেশাগত সেবা মানুষের কাছে তুলে ধরতে চান—উভয়েই এই প্ল্যাটফর্ম ব্যবহার করতে পারবেন।',
    'home.faq.3.q'        => 'JoruriKaj.com কীভাবে ব্যবহার করব?',
    'home.faq.3.a'        => 'আপনার প্রয়োজন অনুযায়ী সেবাদানকারী খুঁজুন অথবা নিজের পেশাগত পরিচিতি নিবন্ধন করুন। এরপর প্ল্যাটফর্মে উপলভ্য তথ্য ও যোগাযোগের পদ্ধতি অনুসরণ করে প্রয়োজনীয় পদক্ষেপ নিন।',
    'home.faq.4.q'        => 'আমার প্রয়োজনীয় কাজের জন্য উপযুক্ত ব্যক্তি কীভাবে খুঁজব?',
    'home.faq.4.a'        => 'আপনার প্রয়োজন অনুযায়ী সেবার ধরন, পেশা বা প্রাসঙ্গিক তথ্য ব্যবহার করে তালিকাভুক্ত সেবাদানকারীদের খুঁজুন। তাঁদের প্রোফাইলের তথ্য দেখে উপযুক্ত ব্যক্তিকে বেছে নিন।',
    'home.faq.5.q'        => 'সেবাদানকারীর সঙ্গে কীভাবে যোগাযোগ করব?',
    'home.faq.5.a'        => 'সেবাদানকারীর প্রোফাইলে দেওয়া যোগাযোগের তথ্য বা JoruriKaj.com-এ উপলভ্য যোগাযোগের মাধ্যম ব্যবহার করুন। কাজের ধরন, সময় ও পারিশ্রমিক সম্পর্কে আগে আলোচনা করে নিন।',
    'home.faq.button'     => 'আরো জানতে ভিজিট করুন',
    'home.feedback.title'       => 'আপনার যেকোন মতামত সাদরে গ্রহণযোগ্য',
    'home.feedback.name'        => 'আপনার নাম',
    'home.feedback.email'       => 'ইমেইল ঠিকানা',
    'home.feedback.phone'       => 'ফোন নাম্বার',
    'home.feedback.subject'     => 'বিষয় বস্তু',
    'home.feedback.message'     => 'আপনার পরামর্শ / মতামত',
    'home.feedback.submit'      => 'মতামত জমা দিন',
    'home.map.alt'              => 'অফিসের অবস্থান — কাকলী, নিউ এয়ারপোর্ট রোড, ঢাকা',

    // ---- Footer ----------------------------------------------------------
    'footer.newsletter'   => 'আপনার ইমেইল - আমাদের সাথে সেতুবন্ধন',
    'footer.hotline'      => 'Hotline:',
    'footer.social_title' => 'সামাজিক যোগাযোগ মাধ্যম',
    'footer.copyright_1'  => '© Copyright 2026 All Rights Reserved by ',
    'footer.copyright_2'  => '.  Developed by ',
    'footer.developer'    => 'Do Something',
    'footer.policy'       => 'Privacy Policy',
    'footer.terms'        => 'Terms & Conditions',
];
