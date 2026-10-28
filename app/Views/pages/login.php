<?php
/**
 * লগইন (Figma node 2660-816 পেশাজীবী, 2686-619 প্রতিনিধি): লগইন আইডি = রেজিস্টার্ড মোবাইল নাম্বার।
 * দুই ধরনের সদস্যের আলাদা পেইজ, একই বিন্যাস; কোনটা তা $role-এ আসে।
 *
 * @var string $heading     ব্যানারের বড় লেখা
 * @var string $subheading  শিরোনাম (টিল কার্ডের ওপরে)
 * @var string $crumb       ব্রেডক্রামের শেষ নাম
 * @var string $role        'worker' | 'agent'
 * @var string $action      ফর্মের ঠিকানা
 * @var list<string> $errors
 * @var string $oldPhone
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, $crumb]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$otherRole = $role === 'agent' ? 'worker' : 'agent';
?>
<section class="section section--login" aria-labelledby="login-title">
    <div class="container">
        <h2 id="login-title" class="section__title login__title"><?= e($subheading) ?></h2>

        <form class="login-card" id="form" action="<?= e($action) ?>" method="post" novalidate>
            <?php if ($errors): ?>
                <ul class="notice notice--error login-card__errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?= csrf_field() ?>
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="login-card__row">
                <label for="lg-id"><?= t('login.f.id') ?></label>
                <div class="lg-field">
                    <input id="lg-id" type="text" name="login_id" maxlength="150" required autocomplete="username" autocapitalize="none" spellcheck="false"
                           placeholder="<?= e(content('login.f.id_hint')) ?>" value="<?= e($oldPhone) ?>">
                </div>
            </div>
            <div class="login-card__row">
                <label for="lg-pass"><?= t('login.f.password') ?></label>
                <div class="lg-field">
                    <input id="lg-pass" type="password" name="password" maxlength="200" required autocomplete="current-password">
                    <?php // বাটন লুকানো (hidden) থাকে; app.js দেখায়। JavaScript না থাকলে পাসওয়ার্ড ঢাকা অবস্থাতেই কাজ করে ?>
                    <button class="lg-eye" type="button" hidden aria-pressed="false" aria-controls="lg-pass"
                            data-show="<?= e(content('login.f.show')) ?>" data-hide="<?= e(content('login.f.hide')) ?>"
                            aria-label="<?= e(content('login.f.show')) ?>"></button>
                </div>
            </div>
            <p class="login-card__submit">
                <button class="lg-submit" type="submit"><?= t('login.submit') ?></button>
            </p>
        </form>

        <?php require BASE_PATH . '/app/Views/partials/forgot-help.php'; ?>
        <p class="login__links">
            <a href="<?= $otherRole === 'agent' ? '/login/agent' : '/login' ?>"><?= e(content('login.other.' . $otherRole)) ?></a>
            <span aria-hidden="true">·</span>
            <a href="/registration"><?= t('login.register') ?></a>
        </p>
    </div>
</section>

<?php // "পাসওয়ার্ড মনে নেই" পপ-আপ: ইমেইল → ৬ সংখ্যার কোড → নতুন পাসওয়ার্ড ?>
<?php $doneLabel = content('forgot.login'); $forgotRole = $role; require BASE_PATH . '/app/Views/partials/forgot-dialog.php'; ?>
