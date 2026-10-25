<?php
/**
 * লগইনের দ্বিতীয় ধাপ: ইমেইলে যাওয়া ৬ সংখ্যার কোড।
 *
 * @var string $maskedEmail
 */
?>
<h1 class="adm-card__title">ইমেইলের কোড</h1>
<p><?= e($maskedEmail) ?> ঠিকানায় একটি ৬ সংখ্যার কোড পাঠানো হয়েছে (১০ মিনিট কাজ করবে)।</p>
<form action="<?= admin_base() ?>/verify" method="post" novalidate>
    <?= csrf_field() ?>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <label for="vf-code">কোড</label>
    <input id="vf-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required autofocus>
    <button class="adm-btn adm-btn--wide" type="submit">লগইন করুন</button>
</form>
<form class="adm-inline" action="<?= admin_base() ?>/verify/resend" method="post">
    <?= csrf_field() ?>
    <button class="adm-link" type="submit">কোড আসেনি? আবার পাঠান</button>
</form>
<p class="adm-links"><a href="<?= admin_base() ?>/login">শুরু থেকে লগইন করুন</a></p>
