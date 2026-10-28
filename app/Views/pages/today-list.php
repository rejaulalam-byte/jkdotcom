<?php
/**
 * "কাজ সম্পর্কে জানুন" (Figma node 2675-148): আজকের প্রকাশিত বিজ্ঞপ্তি সারি আকারে; ক্লিক করলে একটা খোলে।
 * ফোন নাম্বার ও ইমেইল দর্শক নাম্বারে ক্লিক না করা পর্যন্ত লুকানো (app.js /today/contact-এ জিজ্ঞেস করে)।
 *
 * @var string $heading
 * @var list<array<string,string|int>> $jobs
 * @var string $token  "নাম্বার দেখান" অনুরোধের CSRF টোকেন
 */
$crumbs = [['/', 'হোম'], ['/today', 'আজকের প্রকাশিত'], [null, 'কাজ সম্পর্কে জানুন']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--today">
    <div class="container">
        <p class="today-intro"><?= t('today.list.intro') ?></p>

        <div class="job-list" data-token="<?= e($token) ?>" data-limit="<?= e(content('today.list.too_many')) ?>">
            <?php if ($jobs === []): ?>
                <p class="job-empty"><?= t('today.list.empty') ?></p>
            <?php endif; ?>

            <?php foreach ($jobs as $i => $job): ?>
                <?php // FAQ-র মতো accordion: একসঙ্গে একটাই খোলা; লোডের সময় সব বিজ্ঞপ্তি বন্ধ ?>
                <details class="job" name="job">
                    <summary>
                        <span class="job__head">
                            <span class="job__title"><?= e($job['title']) ?></span>
                            <span class="job__meta">
                                <span><?= t('today.list.date') ?> <?= e($job['date_bn']) ?> ।</span>
                                <span><?= t('today.list.time') ?> <?= e($job['time_bn']) ?></span>
                            </span>
                        </span>
                        <span class="job__toggle" aria-hidden="true"></span>
                    </summary>
                    <div class="job__body">
                        <p class="job__label"><?= t('today.list.details') ?></p>
                        <p><?= nl2br(e($job['details'])) ?></p>

                        <p class="job__label"><?= t('today.list.contact') ?></p>
                        <p><?= e($job['contact_line']) ?></p>

                        <p class="job__row">
                            <?= t('today.list.phone') ?>
                            <button class="job-reveal" type="button" data-id="<?= (int) $job['id'] ?>" data-kind="phone"><?= e($job['phone_masked']) ?></button>
                            <span class="job__hint"><?= t('today.list.phone_hint') ?></span>
                        </p>
                        <?php if ($job['email_masked'] !== ''): ?>
                            <p class="job__row"><?= t('today.list.email') ?> <button class="job-reveal" type="button" data-id="<?= (int) $job['id'] ?>" data-kind="email"><?= e($job['email_masked']) ?></button></p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<?php require BASE_PATH . '/app/Views/partials/lead-dialog.php'; ?>
