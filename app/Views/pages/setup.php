<?php
/**
 * পুরনো সাইট থেকে আনা কর্মীর প্রথম লগইনের "তথ্য পূর্ণ করুন" পেইজ (Figma-তে নকশা নেই; লগইন কার্ডের বিন্যাসই)।
 * নতুন পাসওয়ার্ড (চোখ-বাটনসহ), ইমেইল, ঠিকানা, মোবাইল (না থাকলে) ও সম্মতির টিক।
 *
 * @var string $heading
 * @var array<string,mixed> $user
 * @var array<string,string> $old     ভুল জমার পর আগের লেখা (email, address, phone)
 * @var list<string> $errors
 */
$crumbs = [['/', 'হোম'], [null, content('setup.crumb')]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$needsPhone = (string) ($user['phone'] ?? '') === '';
$val = static fn (string $name, string $fallback = ''): string => e((string) ($old[$name] ?? $fallback));
// [id, name, label-কী, type, autocomplete, বাড়তি অ্যাট্রিবিউট]
$pwRows = [
    ['st-pass', 'password', 'setup.f.password', 'new-password'],
    ['st-pass2', 'password_confirm', 'setup.f.password2', 'new-password'],
];
?>
<section class="section section--login" aria-labelledby="st-title">
    <div class="container">
        <h2 id="st-title" class="section__title login__title"><?= e(content('setup.title')) ?></h2>
        <p class="login__notice notice notice--ok"><?= e(honorific_name($user['gender'] ?? null, (string) $user['name'])) ?>, <?= e(content('setup.intro')) ?></p>

        <form class="login-card" id="form" action="/account/setup" method="post" novalidate <?= form_msg_attrs() ?>>
            <?php if ($errors): ?>
                <ul class="notice notice--error login-card__errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?= csrf_field() ?>
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            <?php foreach ($pwRows as [$id, $name, $labelKey, $autocomplete]): ?>
                <div class="login-card__row">
                    <label for="<?= e($id) ?>"><?= e(content($labelKey)) ?></label>
                    <div class="lg-field">
                        <input id="<?= e($id) ?>" type="password" name="<?= e($name) ?>" minlength="8" maxlength="72" required autocomplete="<?= e($autocomplete) ?>">
                        <?php // বাটন লুকানো থাকে; app.js দেখায় (JavaScript ছাড়া পাসওয়ার্ড ঢাকাই থাকে) ?>
                        <button class="lg-eye" type="button" hidden aria-pressed="false" aria-controls="<?= e($id) ?>"
                                data-show="<?= e(content('login.f.show')) ?>" data-hide="<?= e(content('login.f.hide')) ?>"
                                aria-label="<?= e(content('login.f.show')) ?>"></button>
                    </div>
                </div>
            <?php endforeach; ?>
            <p class="pw-note"><?= e(content('regform.password_note')) ?></p>

            <div class="login-card__row">
                <label for="st-email"><?= e(content('setup.f.email')) ?></label>
                <div class="lg-field">
                    <input id="st-email" type="email" name="email" maxlength="150" required autocomplete="email" inputmode="email" autocapitalize="none" value="<?= $val('email') ?>">
                </div>
            </div>
            <?php if ($needsPhone): ?>
                <div class="login-card__row">
                    <label for="st-phone"><?= e(content('setup.f.phone')) ?></label>
                    <div class="lg-field">
                        <input id="st-phone" type="tel" name="phone" maxlength="14" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}" value="<?= $val('phone') ?>">
                    </div>
                </div>
            <?php endif; ?>
            <div class="login-card__row">
                <label for="st-address"><?= e(content('setup.f.address')) ?></label>
                <div class="lg-field">
                    <input id="st-address" data-bn type="text" name="address" maxlength="250" required autocomplete="street-address" value="<?= $val('address', (string) ($user['address'] ?? '')) ?>">
                </div>
            </div>
            <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

            <?php $consentKey = 'worker'; $consentChecked = false; require BASE_PATH . '/app/Views/partials/consent.php'; ?>

            <p class="login-card__submit">
                <button class="lg-submit" type="submit"><?= e(content('setup.submit')) ?></button>
            </p>
        </form>
        <p class="login__help"><?= e(content('setup.help')) ?></p>
    </div>
</section>
