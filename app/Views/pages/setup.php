<?php
/**
 * পুরনো সাইট থেকে আনা কর্মীর প্রথম লগইনের "তথ্য পূর্ণ করুন" পেইজ (Figma-তে নকশা নেই; লগইন কার্ডের বিন্যাসই)।
 * নতুন পাসওয়ার্ড (চোখ-বাটনসহ), জেন্ডার, ইমেইল, মোবাইল (না থাকলে), ঠিকানা, উপজেলা/জেলা/বিভাগ, সম্মতির টিক আবশ্যক (লাল *); দুই রেফারেন্স ঐচ্ছিক:
 * যে তথ্য বাকি থাকে সার্ভার সেটার নাম ধরে জানায়।
 *
 * @var string $heading
 * @var array<string,mixed> $user
 * @var array<string,mixed> $old     ভুল জমার পর আগের লেখা (email, address, phone, gender, upazila_id, district_id, division_id, reference_1/2)
 * @var array{divisions:list<array<string,mixed>>,districts:list<array<string,mixed>>,upazilas:list<array<string,mixed>>} $geo
 * @var list<string> $errors
 */
$crumbs = [['/', 'হোম'], [null, content('setup.crumb')]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$needsPhone = (string) ($user['phone'] ?? '') === '';
$val = static fn (string $name, string $fallback = ''): string => e((string) ($old[$name] ?? $fallback));
// জমা ব্যর্থ হলে আগের বাছাই, নইলে সদস্যের এখনকার মান
$cur = static fn (string $name): int => (int) ($old[$name] ?? $user[$name] ?? 0);
$gender = (string) ($old['gender'] ?? $user['gender'] ?? '');
$star = ' <span class="lg-star">*</span>';
$selectAttrs = 'required data-search data-none="' . e(content('geo.search.none')) . '"';
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
        <p class="login__notice notice"><?= e(content('setup.required_note')) ?></p>

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
                    <label for="<?= e($id) ?>"><?= e(content($labelKey)) ?><?= $star ?></label>
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
                <label for="st-gender"><?= e(content('setup.f.gender')) ?><?= $star ?></label>
                <div class="lg-field lg-field--select">
                    <select id="st-gender" name="gender" required>
                        <option value=""><?= e(content('regform.f.gender')) ?></option>
                        <?php foreach (['male', 'female', 'other'] as $option): ?>
                            <option value="<?= $option ?>"<?= $gender === $option ? ' selected' : '' ?>><?= e(content('gender.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="login-card__row">
                <label for="st-email"><?= e(content('setup.f.email')) ?><?= $star ?></label>
                <div class="lg-field">
                    <input id="st-email" type="email" name="email" maxlength="150" required autocomplete="email" inputmode="email" autocapitalize="none" value="<?= $val('email') ?>">
                </div>
            </div>
            <?php if ($needsPhone): ?>
                <div class="login-card__row">
                    <label for="st-phone"><?= e(content('setup.f.phone')) ?><?= $star ?></label>
                    <div class="lg-field">
                        <input id="st-phone" type="tel" name="phone" maxlength="14" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}" value="<?= $val('phone') ?>">
                    </div>
                </div>
            <?php endif; ?>
            <div class="login-card__row">
                <label for="st-address"><?= e(content('setup.f.address')) ?><?= $star ?></label>
                <div class="lg-field">
                    <input id="st-address" data-bn type="text" name="address" maxlength="250" required autocomplete="street-address" value="<?= $val('address', (string) ($user['address'] ?? '')) ?>">
                </div>
            </div>
            <?php // এলাকার তিন ড্রপডাউন: id-গুলো সাইটের অন্য ফর্মের মতোই (pj-*), app.js একই কোডে চালায় ?>
            <div class="login-card__row">
                <label for="pj-upazila"><?= e(content('setup.f.upazila')) ?><?= $star ?></label>
                <div class="lg-field lg-field--select">
                    <select id="pj-upazila" name="upazila_id" <?= $selectAttrs ?> data-all="<?= e(content('geo.all.upazila')) ?>">
                        <option value=""><?= e(content('regform.f.upazila')) ?></option>
                        <?php foreach ($geo['upazilas'] as $u): ?>
                            <option value="<?= $u['id'] ?>" data-parent="<?= $u['parent'] ?>" data-name="<?= e($u['short']) ?>"<?= $cur('upazila_id') === $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="login-card__row">
                <label for="pj-district"><?= e(content('setup.f.district')) ?><?= $star ?></label>
                <div class="lg-field lg-field--select">
                    <select id="pj-district" name="district_id" <?= $selectAttrs ?> data-all="<?= e(content('geo.all.district')) ?>">
                        <option value=""><?= e(content('regform.f.district')) ?></option>
                        <?php foreach ($geo['districts'] as $d): ?>
                            <option value="<?= $d['id'] ?>" data-parent="<?= $d['parent'] ?>"<?= $cur('district_id') === $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="login-card__row">
                <label for="pj-division"><?= e(content('setup.f.division')) ?><?= $star ?></label>
                <div class="lg-field lg-field--select">
                    <select id="pj-division" name="division_id" <?= $selectAttrs ?> data-all="<?= e(content('geo.all.division')) ?>">
                        <option value=""><?= e(content('regform.f.division')) ?></option>
                        <?php foreach ($geo['divisions'] as $v): ?>
                            <option value="<?= $v['id'] ?>"<?= $cur('division_id') === $v['id'] ? ' selected' : '' ?>><?= e($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php foreach ([['st-ref1', 'reference_1', 'setup.f.ref1'], ['st-ref2', 'reference_2', 'setup.f.ref2']] as [$id, $name, $labelKey]): ?>
                <div class="login-card__row">
                    <label for="<?= e($id) ?>"><?= e(content($labelKey)) ?></label>
                    <div class="lg-field lg-field--area">
                        <textarea id="<?= e($id) ?>" data-bn name="<?= e($name) ?>" maxlength="300" rows="3" placeholder="<?= e(content('setup.f.ref_hint')) ?>"><?= $val($name, (string) ($user[$name] ?? '')) ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

            <?php $consentKey = 'worker'; $consentChecked = false; require BASE_PATH . '/app/Views/partials/consent.php'; ?>

            <p class="login-card__submit">
                <button class="lg-submit" type="submit"><?= e(content('setup.submit')) ?></button>
            </p>
        </form>
        <p class="login__help"><?= e(content('setup.help')) ?></p>
    </div>
</section>
