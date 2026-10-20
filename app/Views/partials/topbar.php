<div class="topbar">
    <div class="container topbar__inner">
        <ul class="topbar__contact">
            <li>
                <a href="<?= e(tel(content('site.phone_top'))) ?>">
                    <img src="<?= e(asset('img/icons/phone-top.svg')) ?>" alt="" width="18" height="18">
                    <span><?= e(content('site.phone_top')) ?></span>
                </a>
            </li>
            <li>
                <a href="mailto:<?= e(content('site.email')) ?>">
                    <img src="<?= e(asset('img/icons/mail-top.svg')) ?>" alt="" width="20" height="16">
                    <span><?= e(content('site.email')) ?></span>
                </a>
            </li>
        </ul>
        <p class="topbar__tagline"><span><?= e(content('site.top_tagline_brand')) ?></span><?= e(content('site.top_tagline')) ?></p>
        <?php $socialClass = 'social--top'; require BASE_PATH . '/app/Views/partials/social.php'; ?>
    </div>
</div>
