<?php
/**
 * মতামতের ফর্ম (পাঁচটি ঘরই আবশ্যক)। হোম ও যোগাযোগ পেইজ একই ফর্ম নেয়; জমার পর যে পেইজ থেকে এসেছিল সেখানেই ফেরে।
 *
 * @var string|null $feedbackOk  সফল বার্তার নাম (msg()-এর কী)
 * @var list<string> $feedbackErrors
 * @var array<string,string> $old
 * @var string $feedbackReturn  ফেরার ঠিকানা ('/#feedback' বা '/contact#feedback'; FeedbackController-এর তালিকায় থাকতে হবে)
 * @var string $feedbackClass   ফর্মের বাড়তি CSS ক্লাস (ঐচ্ছিক)
 */
?>
<form class="feedback<?= !empty($feedbackClass) ? ' ' . e($feedbackClass) : '' ?>" id="feedback" action="/feedback" method="post" novalidate>
    <h3 class="feedback__title"><?= t('home.feedback.title') ?></h3>
    <?php require BASE_PATH . '/app/Views/partials/bn-switch.php'; ?>

    <?php if ($feedbackOk): // $feedbackOk = বার্তার নাম (feedback.msg.done বা subscribe.msg.done); বাক্স অ্যাডমিনের বাছা ডিজাইনে
        $msgName = (string) $feedbackOk;
        $msgLines = [e(msg($msgName))];
        $msgHome = false;
        $msgPlace = 'inline';
        require BASE_PATH . '/app/Views/partials/message-box.php';
    endif; ?>
    <?php if ($feedbackErrors): ?>
        <ul class="notice notice--error" role="alert">
            <?php foreach ($feedbackErrors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($feedbackReturn ?? '/#feedback') ?>">
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

    <?php // প্রতিটি ঘর আবশ্যক: প্রতিটি ইঙ্গিত-লেখার শেষে "*" ?>
    <div class="field">
        <label class="sr-only" for="fb-name"><?= t('home.feedback.name') ?></label>
        <input id="fb-name" data-bn type="text" name="name" maxlength="100" required autocomplete="name"
               placeholder="<?= e(content('home.feedback.name')) ?> *" value="<?= e($old['name'] ?? '') ?>">
    </div>

    <div class="field">
        <label class="sr-only" for="fb-email"><?= t('home.feedback.email') ?></label>
        <input id="fb-email" type="email" name="email" maxlength="150" required autocomplete="email"
               placeholder="<?= e(content('home.feedback.email')) ?> *" value="<?= e($old['email'] ?? '') ?>">
    </div>

    <div class="field">
        <label class="sr-only" for="fb-phone"><?= t('home.feedback.phone') ?></label>
        <input id="fb-phone" type="tel" name="phone" maxlength="20" required autocomplete="tel"
               placeholder="<?= e(content('home.feedback.phone')) ?> *" value="<?= e($old['phone'] ?? '') ?>">
    </div>

    <div class="field">
        <label class="sr-only" for="fb-subject"><?= t('home.feedback.subject') ?></label>
        <input id="fb-subject" data-bn type="text" name="subject" maxlength="150" required
               placeholder="<?= e(content('home.feedback.subject')) ?> *" value="<?= e($old['subject'] ?? '') ?>">
    </div>

    <div class="field">
        <label class="sr-only" for="fb-message"><?= t('home.feedback.message') ?></label>
        <textarea id="fb-message" data-bn name="message" rows="5" maxlength="2000" required
                  placeholder="<?= e(content('home.feedback.message')) ?> *"><?= e($old['message'] ?? '') ?></textarea>
    </div>

    <button class="btn btn--white" type="submit">
        <span><?= t('home.feedback.submit') ?></span>
        <img src="<?= e(asset('img/icons/paper-plane.svg')) ?>" alt="" width="30" height="29">
    </button>
</form>
