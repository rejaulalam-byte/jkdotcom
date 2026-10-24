<?php /** পাসওয়ার্ড ভুলে যাওয়া: ইমেইল দিলে বদলের এককালীন লিংক যায়। */ ?>
<h1 class="adm-card__title">পাসওয়ার্ড ভুলে গেছেন</h1>
<p>আপনার অ্যাডমিন ইমেইলটি লিখুন। পাসওয়ার্ড বদলের একটি লিংক পাঠানো হবে (১ ঘণ্টা কাজ করবে)।</p>
<form action="/admin/forgot" method="post" novalidate>
    <?= csrf_field() ?>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label for="fg-email">ইমেইল</label>
    <input id="fg-email" type="email" name="email" required autocomplete="username" autocapitalize="none" maxlength="150">
    <button class="adm-btn adm-btn--wide" type="submit">লিংক পাঠান</button>
</form>
<p class="adm-links"><a href="/admin/login">লগইনে ফিরুন</a></p>
