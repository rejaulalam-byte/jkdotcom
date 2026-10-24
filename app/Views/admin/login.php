<?php
/**
 * অ্যাডমিন লগইন: ইমেইল + পাসওয়ার্ড; ঠিক হলে ইমেইলে ৬ সংখ্যার কোড যায় (পরের ধাপ /admin/verify)।
 *
 * @var array<string,string> $old
 */
?>
<h1 class="adm-card__title">অ্যাডমিন লগইন</h1>
<form action="/admin/login" method="post" novalidate>
    <?= csrf_field() ?>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label for="lg-email">ইমেইল</label>
    <input id="lg-email" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="username" autocapitalize="none" maxlength="150">
    <label for="lg-pass">পাসওয়ার্ড</label>
    <div class="adm-pass">
        <input id="lg-pass" type="password" name="password" required autocomplete="current-password" maxlength="200">
        <button class="adm-eye" type="button" hidden aria-pressed="false" aria-controls="lg-pass" aria-label="পাসওয়ার্ড দেখুন" data-show="পাসওয়ার্ড দেখুন" data-hide="পাসওয়ার্ড লুকান"></button>
    </div>
    <button class="adm-btn adm-btn--wide" type="submit">এগিয়ে যান</button>
</form>
<p class="adm-links"><a href="/admin/forgot">পাসওয়ার্ড ভুলে গেছেন?</a></p>
