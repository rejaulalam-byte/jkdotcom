<?php
/**
 * যোগাযোগ পেইজ (Figma node 2692-2): ওপরে পরিচিতি-লেখা ও চারটি যোগাযোগ-কার্ড, মাঝে প্রধান নির্বাহীর ছবি,
 * নিচে হালকা টিল ব্যান্ডে বাঁয়ে মতামতের ফর্ম, ডানে গুগল ম্যাপ (হোমের মতো ছবি, ক্লিকে গুগল ম্যাপ খোলে)।
 *
 * @var string $heading
 * @var string|null $feedbackOk
 * @var list<string> $feedbackErrors
 * @var array<string,string> $old
 */
$crumbs = [['/', 'হোম'], [null, 'যোগাযোগ']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--contact">
    <div class="container">
        <p class="contact-intro"><?= e(content('contact.intro')) ?></p>

        <div class="contact-cards">
            <?php // ১. ঠিকানা ?>
            <article class="contact-card">
                <img src="<?= e(asset('img/site/contact-address.svg')) ?>" alt="" width="81" height="81">
                <p class="contact-card__text">
                    <?= e(content('site.address_1')) ?><br>
                    <?= e(content('site.address_2')) ?><br>
                    <?= e(content('site.address_3')) ?>
                </p>
                <p class="contact-card__note"><?= colored(content('contact.holiday')) ?></p>
            </article>

            <?php // ২. ফোন ও অফিস-সময় ?>
            <article class="contact-card">
                <img src="<?= e(asset('img/site/contact-phone.svg')) ?>" alt="" width="81" height="81">
                <p class="contact-card__text"><?= e(content('contact.mobile')) ?> <a href="<?= e(tel(content('site.phone_top'))) ?>"><?= e(content('site.phone_top')) ?></a></p>
                <p class="contact-card__text"><?= e(content('contact.hotline')) ?> <a class="contact-card__strong" href="<?= e(tel(content('site.hotline'))) ?>"><?= e(content('site.hotline')) ?></a></p>
                <p class="contact-card__note"><strong><?= e(content('contact.open')) ?></strong><br><?= e(content('contact.open_time')) ?></p>
            </article>

            <?php // ৩. ইমেইল ?>
            <article class="contact-card">
                <img src="<?= e(asset('img/site/contact-email.svg')) ?>" alt="" width="81" height="81">
                <p class="contact-card__text"><?= e(content('contact.communication')) ?><br><a class="contact-card__strong" href="mailto:<?= e(content('site.email')) ?>"><?= e(content('site.email')) ?></a></p>
                <p class="contact-card__text"><?= e(content('contact.registration')) ?><br><a class="contact-card__strong" href="mailto:<?= e(content('site.email_registration')) ?>"><?= e(content('site.email_registration')) ?></a></p>
            </article>

            <?php // ৪. ওয়েবসাইট ও অবস্থান ?>
            <article class="contact-card">
                <img src="<?= e(asset('img/site/contact-web.svg')) ?>" alt="" width="81" height="81">
                <p class="contact-card__text"><?= e(content('contact.web')) ?> <a class="contact-card__strong" href="/"><?= e(content('site.website')) ?></a></p>
                <p class="contact-card__text"><?= e(content('contact.location')) ?>
                    <a href="https://www.google.com/maps?q=<?= e(rawurlencode(str_replace(' ', '', content('site.coordinates')))) ?>" target="_blank" rel="noopener noreferrer"><?= e(content('site.coordinates')) ?></a>
                </p>
            </article>
        </div>
    </div>
</section>

<section class="section section--ceo" aria-labelledby="ceo-title">
    <div class="container">
        <h2 class="ceo__title" id="ceo-title"><?= e(content('contact.ceo.heading')) ?></h2>
        <figure class="ceo">
            <div class="ceo__photo">
                <img src="<?= e(asset('img/site/ceo.png')) ?>" alt="<?= e(content('contact.ceo.alt')) ?>" width="512" height="620" loading="lazy">
            </div>
            <figcaption>
                <strong class="ceo__name"><?= e(content('contact.ceo.name')) ?></strong>
                <span class="ceo__role"><?= e(content('contact.ceo.role')) ?></span>
            </figcaption>
        </figure>
    </div>
</section>

<section class="section contact-faq" aria-labelledby="contact-faq-title">
    <div class="container">
        <h2 class="contact-faq__title" id="contact-faq-title"><?= e(content('home.faq.title')) ?></h2>
        <p class="contact-faq__text"><?= e(content('home.faq.text')) ?></p>

        <div class="contact-split">
            <?php $feedbackReturn = '/contact#feedback'; $feedbackClass = 'feedback--contact'; require BASE_PATH . '/app/Views/partials/feedback-form.php'; ?>
            <a class="contact-map" href="https://www.google.com/maps?q=<?= e(rawurlencode(str_replace(' ', '', content('site.coordinates')))) ?>" target="_blank" rel="noopener noreferrer">
                <img src="<?= e(asset('img/site/map-location.png')) ?>" alt="<?= e(content('home.map.alt')) ?>" width="1221" height="580" loading="lazy">
            </a>
        </div>
    </div>
</section>
