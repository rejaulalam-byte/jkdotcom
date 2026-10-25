<?php
/**
 * ইমেইলের এককালীন লিংকে পাসওয়ার্ড বসানো (নতুন অ্যাডমিনের আমন্ত্রণ ও পাসওয়ার্ড ভুলে যাওয়া, দুটোই)।
 *
 * @var bool $valid
 * @var string $adminName
 * @var string $token
 */
?>
<h1 class="adm-card__title">পাসওয়ার্ড বসান</h1>
<?php if (!$valid): ?>
    <p>এই লিংকটির মেয়াদ শেষ হয়ে গেছে বা আগেই ব্যবহার হয়েছে।</p>
    <p class="adm-links"><a href="<?= admin_base() ?>/forgot">নতুন লিংক চান</a></p>
<?php else: ?>
    <p><?= e($adminName) ?>, আপনার নতুন পাসওয়ার্ড দিন (কমপক্ষে ১০ অক্ষর)।</p>
    <form action="<?= admin_base() ?>/set-password/<?= e($token) ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <?php foreach ([['sp-pass', 'password', 'নতুন পাসওয়ার্ড'], ['sp-pass2', 'password_confirm', 'পাসওয়ার্ড আবার']] as [$id, $name, $label]): ?>
            <label for="<?= $id ?>"><?= e($label) ?></label>
            <div class="adm-pass">
                <input id="<?= $id ?>" type="password" name="<?= $name ?>" required autocomplete="new-password" minlength="10" maxlength="72">
                <button class="adm-eye" type="button" hidden aria-pressed="false" aria-controls="<?= $id ?>" aria-label="পাসওয়ার্ড দেখুন" data-show="পাসওয়ার্ড দেখুন" data-hide="পাসওয়ার্ড লুকান"></button>
            </div>
        <?php endforeach; ?>
        <button class="adm-btn adm-btn--wide" type="submit">পাসওয়ার্ড বসান</button>
    </form>
<?php endif; ?>
