<?php
/**
 * প্রথম সুপার অ্যাডমিন বানানো। কোনো অ্যাডমিন থাকলে এই পেইজ নেই (404)। সার্ভারের .env-এ ADMIN_SETUP_KEY না থাকলে বন্ধ।
 *
 * @var bool $enabled
 * @var array<string,string> $old
 */
?>
<h1 class="adm-card__title">প্রথম সুপার অ্যাডমিন</h1>
<?php if (!$enabled): ?>
    <p>এই পেইজ এখন বন্ধ। চালু করতে সার্ভারের গোপন ফাইলে (<code>.env</code>) একটি লাইন যোগ করুন:</p>
    <p><code>ADMIN_SETUP_KEY=আপনার-নিজের-দীর্ঘ-গোপন-কী</code></p>
    <p>তারপর এই পেইজ রিফ্রেশ করুন। কী-টি শুধু আপনি জানবেন; অ্যাডমিন তৈরি হলে পেইজটি চিরতরে বন্ধ হয়ে যাবে।</p>
<?php else: ?>
    <p>সেটআপ-কী (সার্ভারের <code>.env</code>-এ যা রেখেছেন) ও নিজের তথ্য দিন। এটা একবারই চলবে।</p>
    <form action="/admin/setup" method="post" novalidate>
        <?= csrf_field() ?>
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label for="su-key">সেটআপ-কী</label>
        <input id="su-key" type="password" name="setup_key" required autocomplete="off" maxlength="200">
        <label for="su-name">আপনার নাম</label>
        <input id="su-name" type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required maxlength="100">
        <label for="su-email">ইমেইল (কোড এখানে আসবে)</label>
        <input id="su-email" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocapitalize="none" maxlength="150">
        <?php foreach ([['su-pass', 'password', 'পাসওয়ার্ড (কমপক্ষে ১০ অক্ষর)'], ['su-pass2', 'password_confirm', 'পাসওয়ার্ড আবার']] as [$id, $name, $label]): ?>
            <label for="<?= $id ?>"><?= e($label) ?></label>
            <div class="adm-pass">
                <input id="<?= $id ?>" type="password" name="<?= $name ?>" required autocomplete="new-password" minlength="10" maxlength="72">
                <button class="adm-eye" type="button" hidden aria-pressed="false" aria-controls="<?= $id ?>" aria-label="পাসওয়ার্ড দেখুন" data-show="পাসওয়ার্ড দেখুন" data-hide="পাসওয়ার্ড লুকান"></button>
            </div>
        <?php endforeach; ?>
        <button class="adm-btn adm-btn--wide" type="submit">সুপার অ্যাডমিন তৈরি করুন</button>
    </form>
<?php endif; ?>
