<?php
use App\Core\Security;

$feedbackToken = Security::csrfToken();
?>
<footer class="site-footer">
    <div class="container footer__grid">
        <?php // head = গ্রিডের সারি ১, body = সারি ২: লিংকের কলাম ও সোশ্যাল বাক্স (এরাও সারি ২) ঠিকানার লাইন থেকে শুরু ?>
        <div class="footer__brand">
            <a class="footer__brand-head" href="#top" aria-label="পেজের উপরে যান">
            <span class="emblem emblem--footer"><img src="<?= e(asset('img/site/logo-figma.png')) ?>" alt="" width="596" height="541" loading="lazy"></span>
            <div class="footer__brand-text">
                <p class="footer__name"><span><?= t('site.brand') ?></span><?= t('site.brand_suffix') ?></p>
                <p class="footer__tagline"><?= t('site.tagline') ?></p>
            </div>
            </a>

            <div class="footer__brand-body">
            <ul class="footer__contact">
                <li>
                    <img src="<?= e(asset('img/icons/home-footer.svg')) ?>" alt="" width="21" height="18">
                    <address>
                        <?= t('site.address_1') ?><br>
                        <?= t('site.address_2') ?><br>
                        <?= t('site.address_3') ?>
                    </address>
                </li>
                <li>
                    <img src="<?= e(asset('img/icons/phone-footer.svg')) ?>" alt="" width="18" height="18">
                    <a href="<?= e(tel(content('site.phone_footer'))) ?>"><?= t('site.phone_footer') ?></a>
                </li>
                <li>
                    <img src="<?= e(asset('img/icons/mail-footer.svg')) ?>" alt="" width="20" height="15">
                    <a href="mailto:<?= e(content('site.email')) ?>"><?= t('site.email') ?></a>
                </li>
                <li>
                    <img src="<?= e(asset('img/icons/web-footer.svg')) ?>" alt="" width="18" height="18">
                    <a href="/"><?= t('site.website') ?></a>
                </li>
                <li>
                    <img src="<?= e(asset('img/icons/location-footer.svg')) ?>" alt="" width="15" height="20">
                    <span><?= t('site.coordinates') ?></span>
                </li>
            </ul>

            <p class="footer__hotline">
                <span><?= t('footer.hotline') ?></span>
                <a href="<?= e(tel(content('site.hotline'))) ?>"><?= t('site.hotline') ?></a>
            </p>
            </div>
        </div>

        <nav class="footer__links" aria-label="ফুটার মেনু">
            <ul>
                <li><a class="footer__links-head" href="/">হোম</a></li>
                <li><a href="/services">আমাদের সেবা সমূহ</a></li>
                <li><a href="/registration">রেজিস্ট্রেশন</a></li>
                <li><a href="/faq">আপনার জিজ্ঞাসা আমাদের উত্তর</a></li>
            </ul>
            <ul>
                <li><a class="footer__links-head" href="/about">আমাদের সম্পর্কে</a></li>
                <li><a href="/today">আজকের প্রকাশিত কাজ</a></li>
                <li><a href="/contact">যোগাযোগ</a></li>
                <li><a href="/stats">এক নজরে আমাদের পরিসংখ্যান</a></li>
            </ul>
        </nav>

        <div class="footer__side">
            <form class="newsletter" action="/subscribe" method="post">
                <?= csrf_field() ?>
                <label class="sr-only" for="newsletter-email"><?= t('footer.newsletter') ?></label>
                <input id="newsletter-email" type="email" name="email" maxlength="150" required
                       placeholder="<?= e(content('footer.newsletter')) ?>" autocomplete="email">
                <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <button type="submit" aria-label="পাঠান">
                    <img src="<?= e(asset('img/icons/send-footer.svg')) ?>" alt="" width="35" height="35">
                </button>
            </form>

            <?php // শিরোনাম সবসময় থাকে; তার নিচে শুধু ঠিকানা-আছে এমন আইকন (কোনো লিংক না থাকলে social.php কিছুই ছাপে না) ?>
            <p <?= pill_attrs('footer.social', 'pill--footer-social') ?>><?= t('footer.social_title') ?></p>
            <?php $socialClass = 'social--footer'; require BASE_PATH . '/app/Views/partials/social.php'; ?>
        </div>
    </div>

    <div class="container footer__bottom">
        <p>
            <?= t('footer.copyright_1') ?><a href="/"><?= e(content('site.top_tagline_brand') !== '' ? strtolower(content('site.top_tagline_brand')) : 'jorurikaj.com') ?></a><?= t('footer.copyright_2') ?><strong><?= t('footer.developer') ?></strong>
        </p>
        <p><a href="/privacy"><?= t('footer.policy') ?></a> - <a href="/terms"><?= t('footer.terms') ?></a></p>
    </div>
</footer>
