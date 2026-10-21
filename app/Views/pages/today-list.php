<?php
/**
 * "কাজ সম্পর্কে জানুন" (Figma node 2675-148): today's published notices as rows; a click opens one.
 * The phone number and e-mail are hidden until the visitor clicks the number (app.js asks /today/contact).
 *
 * @var string $heading
 * @var list<array<string,string|int>> $jobs
 * @var string $token  CSRF token for the "show number" request
 */
$crumbs = [['/', 'হোম'], ['/today', 'আজকের প্রকাশিত'], [null, 'কাজ সম্পর্কে জানুন']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--today">
    <div class="container">
        <p class="today-intro"><?= e(content('today.list.intro')) ?></p>

        <div class="job-list" data-token="<?= e($token) ?>" data-limit="<?= e(content('today.list.too_many')) ?>">
            <?php if ($jobs === []): ?>
                <p class="job-empty"><?= e(content('today.list.empty')) ?></p>
            <?php endif; ?>

            <?php foreach ($jobs as $i => $job): ?>
                <?php // exclusive accordion like the FAQ; the newest notice is open on load ?>
                <details class="job" name="job"<?= $i === 0 ? ' open' : '' ?>>
                    <summary>
                        <span class="job__head">
                            <span class="job__title"><?= e($job['title']) ?></span>
                            <span class="job__meta">
                                <span><?= e(content('today.list.date')) ?> <?= e($job['date_bn']) ?> ।</span>
                                <span><?= e(content('today.list.time')) ?> <?= e($job['time_bn']) ?></span>
                            </span>
                        </span>
                        <span class="job__toggle" aria-hidden="true"></span>
                    </summary>
                    <div class="job__body">
                        <p class="job__label"><?= e(content('today.list.details')) ?></p>
                        <p><?= nl2br(e($job['details'])) ?></p>

                        <p class="job__label"><?= e(content('today.list.contact')) ?></p>
                        <p><?= e($job['contact_line']) ?></p>

                        <p class="job__row">
                            <?= e(content('today.list.phone')) ?>
                            <button class="job-reveal" type="button" data-id="<?= (int) $job['id'] ?>"><?= e($job['phone_masked']) ?></button>
                            <span class="job__hint"><?= e(content('today.list.phone_hint')) ?></span>
                        </p>
                        <?php if ($job['email_masked'] !== ''): ?>
                            <p class="job__row"><?= e(content('today.list.email')) ?> <span class="job__email"><?= e($job['email_masked']) ?></span></p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <p class="today-actions">
            <a class="pf-submit" href="/today/post"><?= e(content('today.list.btn_post')) ?></a>
            <a class="pf-submit" href="/today" aria-current="page"><?= e(content('today.list.btn_list')) ?></a>
        </p>
    </div>
</section>
