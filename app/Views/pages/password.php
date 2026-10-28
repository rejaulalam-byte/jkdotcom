<?php
/**
 * পাসওয়ার্ড পরিবর্তন (লগইন-করা কর্মী ও প্রতিনিধির জন্য)। বিন্যাস লগইন কার্ডের মতোই (Figma-তে এই পেইজের নকশা নেই)।
 *
 * @var string $heading
 * @var bool   $forced   প্রথম লগইনে পাসওয়ার্ড বদলানো বাধ্যতামূলক (must_change_password)
 * @var list<string> $errors
 */
$crumbs = [['/', 'হোম'], [null, content('password.crumb')]];
require BASE_PATH . '/app/Views/partials/page-banner.php';

$rows = [
    ['pw-current', 'current_password', 'password.f.current', 'new-password'],
    ['pw-new', 'password', 'password.f.new', 'new-password'],
    ['pw-confirm', 'password_confirm', 'password.f.confirm', 'new-password'],
];
?>
<section class="section section--login" aria-labelledby="pw-title">
    <div class="container">
        <h2 id="pw-title" class="section__title login__title"><?= t('password.title') ?></h2>

        <?php if ($forced): ?>
            <p class="notice notice--error login__notice"><?= t('password.forced') ?></p>
        <?php endif; ?>

        <form class="login-card" id="form" action="/account/password" method="post" novalidate <?= form_msg_attrs() ?>>
            <?php if ($errors): ?>
                <ul class="notice notice--error login-card__errors" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?= csrf_field() ?>
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            <?php foreach ($rows as [$id, $name, $labelKey, $autocomplete]): ?>
                <div class="login-card__row">
                    <label for="<?= e($id) ?>"><?= e(content($labelKey)) ?></label>
                    <div class="lg-field">
                        <input id="<?= e($id) ?>" type="password" name="<?= e($name) ?>" maxlength="72" required autocomplete="<?= e($autocomplete) ?>"<?= $name === 'current_password' ? ' data-no-autofill' : '' ?><?= $name !== 'current_password' ? ' minlength="8"' : '' ?>>
                        <?php // বাটন লুকানো থাকে; app.js দেখায় (JavaScript ছাড়া পাসওয়ার্ড ঢাকাই থাকে) ?>
                        <button class="lg-eye" type="button" hidden aria-pressed="false" aria-controls="<?= e($id) ?>"
                                data-show="<?= e(content('login.f.show')) ?>" data-hide="<?= e(content('login.f.hide')) ?>"
                                aria-label="<?= e(content('login.f.show')) ?>"></button>
                    </div>
                </div>
            <?php endforeach; ?>
            <p class="pw-note"><?= t('regform.password_note') ?></p>
            <p class="login-card__submit">
                <button class="lg-submit" type="submit"><?= t('password.submit') ?></button>
            </p>
        </form>

        <?php require BASE_PATH . '/app/Views/partials/forgot-help.php'; ?>
    </div>
</section>

<?php // "পাসওয়ার্ড মনে নেই" পপ-আপ: বর্তমান পাসওয়ার্ড মনে না থাকলে ইমেইলের কোড দিয়ে নতুন পাসওয়ার্ড ?>
<?php $doneLabel = content('forgot.ok'); $forgotRole = (string) (\App\Core\Auth::user()['role'] ?? 'worker'); require BASE_PATH . '/app/Views/partials/forgot-dialog.php'; ?>
