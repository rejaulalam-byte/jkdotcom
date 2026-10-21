<?php
/**
 * Registration guideline for professionals (Figma node 2683-148): banner, one open
 * writing area (.rich), two buttons, then a tinted band with two photo cards.
 *
 * @var string $heading
 * @var string $bodyHtml  trusted HTML (admin-editor output, sanitised on save)
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, 'পেশাজীবী রেজিস্ট্রেশনের নিয়মাবলী']];
$regActive = 'pro';
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--open-page section--guide section--regtabs">
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/reg-tabs.php'; ?>
        <div class="rich">
            <?= $bodyHtml ?>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/app/Views/partials/reg-join.php'; ?>
