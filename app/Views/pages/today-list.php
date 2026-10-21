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
                <?php // exclusive accordion like the FAQ; every notice is closed on load (owner 2026-10-21) ?>
                <details class="job" name="job">
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
                            <button class="job-reveal" type="button" data-id="<?= (int) $job['id'] ?>" data-kind="phone"><?= e($job['phone_masked']) ?></button>
                            <span class="job__hint"><?= e(content('today.list.phone_hint')) ?></span>
                        </p>
                        <?php if ($job['email_masked'] !== ''): ?>
                            <p class="job__row"><?= e(content('today.list.email')) ?> <button class="job-reveal" type="button" data-id="<?= (int) $job['id'] ?>" data-kind="email"><?= e($job['email_masked']) ?></button></p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<?php // pop-up shown when a hidden number / e-mail is clicked: "are you interested?" -> name + mobile -> contact shown ?>
<dialog class="lead" id="lead-dialog" aria-labelledby="lead-title">
    <div class="lead__step" data-step="ask">
        <h3 id="lead-title"><?= e(content('today.lead.title')) ?></h3>
        <p><?= e(content('today.lead.ask')) ?></p>
        <p class="lead__actions">
            <button class="pf-submit" type="button" data-act="yes"><?= e(content('today.lead.yes')) ?></button>
            <button class="lead__no" type="button" data-act="no"><?= e(content('today.lead.no')) ?></button>
        </p>
    </div>
    <form class="lead__step" data-step="form" hidden novalidate>
        <h3><?= e(content('today.lead.form_title')) ?></h3>
        <p><?= e(content('today.lead.form_text')) ?></p>
        <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>
        <div class="pf">
            <label class="sr-only" for="lead-name"><?= e(content('today.lead.name')) ?></label>
            <input id="lead-name" data-bn type="text" name="name" maxlength="100" required autocomplete="name" placeholder="<?= e(content('today.lead.name')) ?> *">
        </div>
        <div class="pf pf--phone">
            <label class="sr-only" for="lead-phone"><?= e(content('today.lead.phone')) ?></label>
            <span class="pf__code" aria-hidden="true">+88</span>
            <input id="lead-phone" type="tel" name="phone" maxlength="11" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}" placeholder="<?= e(content('today.lead.phone')) ?> *">
        </div>
        <p class="lead__error" role="alert" hidden></p>
        <p class="lead__actions">
            <button class="pf-submit" type="submit"><?= e(content('today.lead.submit')) ?></button>
            <button class="lead__no" type="button" data-act="no"><?= e(content('today.lead.cancel')) ?></button>
        </p>
    </form>
</dialog>
