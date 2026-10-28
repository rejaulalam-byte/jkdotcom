<?php
/**
 * যোগাযোগ পেইজ (Figma node 2692-2): ওপরে পরিচিতি-লেখা ও চারটি যোগাযোগ-কার্ড, মাঝে প্রধান নির্বাহীর ছবি,
 * নিচে হালকা টিল ব্যান্ডে বাঁয়ে মতামতের ফর্ম, ডানে মানচিত্র (OpenStreetMap-এর ছবি; ক্লিকে গুগল ম্যাপ খোলে)।
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
        <p class="contact-intro"><?= t('contact.intro') ?></p>

        <div class="contact-cards">
            <?php // ১. ঠিকানা ?>
            <article class="contact-card">
                <?= site_image('contact.icon.address', ['width' => 81, 'height' => 81]) ?>
                <p class="contact-card__text">
                    <?= t('site.address_1') ?><br>
                    <?= t('site.address_2') ?><br>
                    <?= t('site.address_3') ?>
                </p>
                <p class="contact-card__note"><?= t('contact.holiday') ?></p>
            </article>

            <?php // ২. ফোন ও অফিস-সময় ?>
            <article class="contact-card">
                <?= site_image('contact.icon.phone', ['width' => 81, 'height' => 81]) ?>
                <p class="contact-card__text"><?= t('contact.mobile') ?> <a href="<?= e(tel(content('site.phone_top'))) ?>"><?= t('site.phone_top') ?></a></p>
                <p class="contact-card__text"><?= t('contact.hotline') ?> <a class="contact-card__strong" href="<?= e(tel(content('site.hotline'))) ?>"><?= t('site.hotline') ?></a></p>
                <p class="contact-card__note"><strong><?= t('contact.open') ?></strong><br><?= t('contact.open_time') ?></p>
            </article>

            <?php // ৩. ইমেইল ?>
            <article class="contact-card">
                <?= site_image('contact.icon.email', ['width' => 81, 'height' => 81]) ?>
                <p class="contact-card__text"><?= t('contact.communication') ?><br><a class="contact-card__strong" href="mailto:<?= e(content('site.email')) ?>"><?= t('site.email') ?></a></p>
                <p class="contact-card__text"><?= t('contact.registration') ?><br><a class="contact-card__strong" href="mailto:<?= e(content('site.email_registration')) ?>"><?= t('site.email_registration') ?></a></p>
            </article>

            <?php // ৪. ওয়েবসাইট ও অবস্থান ?>
            <article class="contact-card">
                <?= site_image('contact.icon.web', ['width' => 81, 'height' => 81]) ?>
                <p class="contact-card__text"><?= t('contact.web') ?> <a class="contact-card__strong" href="/"><?= t('site.website') ?></a></p>
                <p class="contact-card__text"><?= t('contact.location') ?>
                    <a href="https://www.google.com/maps?q=<?= e(rawurlencode(str_replace(' ', '', content('site.coordinates')))) ?>" target="_blank" rel="noopener noreferrer"><?= t('site.coordinates') ?></a>
                </p>
            </article>
        </div>
    </div>
</section>

<section class="section section--ceo" aria-labelledby="ceo-title">
    <div class="container">
        <h2 class="ceo__title" id="ceo-title"><?= t('contact.ceo.heading') ?></h2>
        <figure class="ceo">
            <div class="ceo__photo">
                <?= site_image('contact.ceo', ['alt' => content('contact.ceo.alt'), 'width' => 512, 'height' => 620, 'loading' => 'lazy']) ?>
            </div>
            <figcaption>
                <strong class="ceo__name"><?= t('contact.ceo.name') ?></strong>
                <span class="ceo__role"><?= t('contact.ceo.role') ?></span>
            </figcaption>
        </figure>
    </div>
</section>

<section class="section contact-faq" aria-labelledby="contact-faq-title">
    <div class="container">
        <h2 class="contact-faq__title" id="contact-faq-title"><?= t('home.faq.title') ?></h2>
        <p class="contact-faq__text"><?= t('home.faq.text') ?></p>

        <div class="contact-split">
            <?php $feedbackReturn = '/contact#feedback'; $feedbackClass = 'feedback--contact'; require BASE_PATH . '/app/Views/partials/feedback-form.php'; ?>
            <?php // মানচিত্র: OpenStreetMap-এর ছবি (লাইসেন্সের শর্তে নিচে কৃতজ্ঞতা-লিংক); ক্লিকে গুগল ম্যাপ খোলে ?>
            <div class="contact-map">
                <a href="https://www.google.com/maps?q=<?= e(rawurlencode(str_replace(' ', '', content('site.coordinates')))) ?>" target="_blank" rel="noopener noreferrer">
                    <?= site_image('contact.map', ['alt' => content('home.map.alt'), 'width' => 1005, 'height' => 1280, 'loading' => 'lazy']) ?>
                </a>
                <a class="contact-map__credit" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a>
            </div>
        </div>
    </div>
</section>
