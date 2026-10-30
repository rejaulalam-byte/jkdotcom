<?php
/**
 * পপ-আপ "আপনি কি আগ্রহী?" → নাম + মোবাইল → যোগাযোগ দেখানো (app.js "লুকানো ফোন নাম্বার / ইমেইল" অংশ চালায়)।
 * কাজের তালিকা (/today) ও কর্মী-তালিকা (/services/<slug>) একই পপ-আপ নেয়।
 *
 * @var string $leadPrefix  লেখার কী-র শুরু ('today.lead' বা 'workers.lead'); না থাকলে 'today.lead'-এর লেখা চলে
 */
$lead = static fn (string $key): string => content(($leadPrefix ?? 'today.lead') . '.' . $key, content('today.lead.' . $key));
?>
<dialog class="lead" id="lead-dialog" aria-labelledby="lead-title">
    <div class="lead__step" data-step="ask">
        <h3 id="lead-title"><?= e($lead('title')) ?></h3>
        <p><?= e($lead('ask')) ?></p>
        <p class="lead__actions">
            <button class="pf-submit" type="button" data-act="yes"><?= e($lead('yes')) ?></button>
            <button class="lead__no" type="button" data-act="no"><?= e($lead('no')) ?></button>
        </p>
    </div>
    <form class="lead__step" data-step="form" hidden novalidate>
        <h3><?= e($lead('form_title')) ?></h3>
        <p><?= e($lead('form_text')) ?></p>
        <p class="lead__notice"><?= e($lead('notice')) ?></p>
        <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>
        <div class="pf">
            <label class="sr-only" for="lead-name"><?= e($lead('name')) ?></label>
            <input id="lead-name" data-bn type="text" name="name" maxlength="100" required autocomplete="name" placeholder="<?= e($lead('name')) ?> *">
        </div>
        <div class="pf pf--phone">
            <label class="sr-only" for="lead-phone"><?= e($lead('phone')) ?></label>
            <span class="pf__code" aria-hidden="true">+৮৮</span>
            <input id="lead-phone" type="tel" name="phone" maxlength="11" required autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}" placeholder="<?= e($lead('phone')) ?> *">
        </div>
        <p class="lead__error" role="alert" hidden></p>
        <p class="lead__actions">
            <button class="pf-submit" type="submit"><?= e($lead('submit')) ?></button>
            <button class="lead__no" type="button" data-act="no"><?= e($lead('cancel')) ?></button>
        </p>
    </form>
</dialog>
