<?php
/**
 * নিজের অ্যাকাউন্ট: নাম, পাসওয়ার্ড, ইমেইল বদলানো (ইমেইল বদলাতে নতুন ঠিকানায় কোড যায়)।
 *
 * @var array<string,mixed> $admin
 * @var bool $emailCodePending
 * @var string $newEmail
 */
use App\Core\AdminAuth;

$eye = static fn (string $id): string => '<button class="adm-eye" type="button" hidden aria-pressed="false" aria-controls="' . e($id)
    . '" aria-label="পাসওয়ার্ড দেখুন" data-show="পাসওয়ার্ড দেখুন" data-hide="পাসওয়ার্ড লুকান"></button>';
?>
<p>ইমেইল: <strong><?= e((string) $admin['email']) ?></strong> · ভূমিকা: <strong><?= e(AdminAuth::roleLabel((string) $admin['role'])) ?></strong></p>

<section class="adm-box">
    <h2>নাম বদলান</h2>
    <form action="/admin/account/name" method="post" novalidate>
        <?= csrf_field() ?>
        <label for="ac-name">নাম</label>
        <input id="ac-name" type="text" name="name" value="<?= e((string) $admin['name']) ?>" required maxlength="100">
        <button class="adm-btn" type="submit">সংরক্ষণ করুন</button>
    </form>
</section>

<section class="adm-box">
    <h2>পাসওয়ার্ড বদলান</h2>
    <form action="/admin/account/password" method="post" novalidate>
        <?= csrf_field() ?>
        <?php foreach ([['ac-cur', 'current_password', 'এখনকার পাসওয়ার্ড', 'current-password'], ['ac-new', 'password', 'নতুন পাসওয়ার্ড (কমপক্ষে ১০ অক্ষর)', 'new-password'], ['ac-new2', 'password_confirm', 'নতুন পাসওয়ার্ড আবার', 'new-password']] as [$id, $name, $label, $auto]): ?>
            <label for="<?= $id ?>"><?= e($label) ?></label>
            <div class="adm-pass"><input id="<?= $id ?>" type="password" name="<?= $name ?>" required autocomplete="<?= $auto ?>" maxlength="72"><?= $eye($id) ?></div>
        <?php endforeach; ?>
        <button class="adm-btn" type="submit">পাসওয়ার্ড বদলান</button>
    </form>
</section>

<section class="adm-box" id="email">
    <h2>ইমেইল বদলান</h2>
    <?php if ($emailCodePending): ?>
        <p><strong><?= e($newEmail) ?></strong> ঠিকানায় পাঠানো ৬ সংখ্যার কোডটি লিখুন।</p>
        <form action="/admin/account/email-confirm" method="post" novalidate>
            <?= csrf_field() ?>
            <label for="ac-code">কোড</label>
            <input id="ac-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required>
            <button class="adm-btn" type="submit">ইমেইল বদলান</button>
        </form>
    <?php else: ?>
        <p>নতুন ইমেইলে একটি কোড যাবে; কোড লিখলে তবেই ইমেইল বদলাবে। (ভুল ঠিকানা দিয়ে নিজেকে আটকানো ঠেকাতে।)</p>
        <form action="/admin/account/email" method="post" novalidate>
            <?= csrf_field() ?>
            <label for="ac-email">নতুন ইমেইল</label>
            <input id="ac-email" type="email" name="email" required autocapitalize="none" maxlength="150">
            <label for="ac-cur2">এখনকার পাসওয়ার্ড</label>
            <div class="adm-pass"><input id="ac-cur2" type="password" name="current_password" required autocomplete="current-password" maxlength="72"><?= $eye('ac-cur2') ?></div>
            <button class="adm-btn" type="submit">কোড পাঠান</button>
        </form>
    <?php endif; ?>
</section>
