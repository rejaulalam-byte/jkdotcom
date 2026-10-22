<?php
/**
 * "পাসওয়ার্ড মনে নেই" পপ-আপ (লগইন পেইজ): ১) ইমেইল ২) ইমেইলে আসা ৬ সংখ্যার কোড ৩) নতুন পাসওয়ার্ড ৪) সফল বার্তা।
 * ধাপগুলো app.js-এর "পাসওয়ার্ড মনে নেই" অংশ চালায় (/password/forgot, /verify, /reset)।
 *
 * @var string $doneLabel  সফল-বার্তার বাটনের লেখা (লগইন পেইজে "লগইন করুন", পাসওয়ার্ড পেইজে "ঠিক আছে")
 */
?>
<dialog class="lead forgot" id="forgot-dialog" aria-labelledby="forgot-title"
        data-net="<?= e(content('forgot.net')) ?>" data-token="<?= e(\App\Core\Security::csrfToken()) ?>">
    <h3 id="forgot-title"><?= e(content('forgot.title')) ?></h3>

    <form class="forgot__step" data-step="email" action="/password/forgot" novalidate>
        <p><?= e(content('forgot.email_text')) ?></p>
        <div class="pf">
            <label class="sr-only" for="fg-email"><?= e(content('forgot.email_ph')) ?></label>
            <input id="fg-email" type="email" name="email" maxlength="150" required autocomplete="email" inputmode="email" autocapitalize="none" placeholder="<?= e(content('forgot.email_ph')) ?>">
        </div>
        <p class="lead__error" role="alert" hidden></p>
        <p class="lead__actions">
            <button class="pf-submit" type="submit"><?= e(content('forgot.send')) ?></button>
            <button class="lead__no" type="button" data-act="close"><?= e(content('forgot.cancel')) ?></button>
        </p>
    </form>

    <form class="forgot__step" data-step="code" action="/password/verify" hidden novalidate>
        <p><?= e(content('forgot.code_text')) ?></p>
        <div class="pf">
            <label class="sr-only" for="fg-code"><?= e(content('forgot.code_ph')) ?></label>
            <input id="fg-code" type="text" name="code" maxlength="6" required inputmode="numeric" autocomplete="one-time-code" placeholder="<?= e(content('forgot.code_ph')) ?>">
        </div>
        <p class="lead__error" role="alert" hidden></p>
        <p class="lead__actions">
            <button class="pf-submit" type="submit"><?= e(content('forgot.verify')) ?></button>
            <button class="lead__no" type="button" data-act="close"><?= e(content('forgot.cancel')) ?></button>
        </p>
    </form>

    <form class="forgot__step" data-step="password" action="/password/reset" hidden novalidate>
        <p><?= e(content('forgot.new_text')) ?></p>
        <div class="pf">
            <label class="sr-only" for="fg-pass"><?= e(content('forgot.new_ph')) ?></label>
            <input id="fg-pass" type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="<?= e(content('forgot.new_ph')) ?>">
        </div>
        <div class="pf">
            <label class="sr-only" for="fg-pass2"><?= e(content('forgot.new2_ph')) ?></label>
            <input id="fg-pass2" type="password" name="password_confirm" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="<?= e(content('forgot.new2_ph')) ?>">
        </div>
        <p class="reg-form__note"><?= e(content('regform.password_note')) ?></p>
        <p class="lead__error" role="alert" hidden></p>
        <p class="lead__actions">
            <button class="pf-submit" type="submit"><?= e(content('forgot.ok')) ?></button>
            <button class="lead__no" type="button" data-act="close"><?= e(content('forgot.cancel')) ?></button>
        </p>
    </form>

    <div class="forgot__step" data-step="done" data-title="<?= e(content('forgot.done_title')) ?>" hidden>
        <p><?= e(content('forgot.done_1')) ?></p>
        <p><em><?= e(content('forgot.done_2')) ?></em></p>
        <p class="lead__actions">
            <button class="pf-submit" type="button" data-act="close"><?= e($doneLabel) ?></button>
        </p>
    </div>
</dialog>
