<?php
/**
 * Registration rules overview (Figma node 2631-395): banner + one open writing
 * area (.rich, same as About) + two buttons to the guideline pages.
 *
 * @var string $heading
 * @var string $bodyHtml  trusted HTML (admin-editor output, sanitised on save)
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, 'রেজিস্ট্রেশনের নিয়মাবলী']];
$regActive = 'rules';
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--open-page section--regtabs">
    <div class="container">
        <?php require BASE_PATH . '/app/Views/partials/reg-tabs.php'; ?>
        <div class="rich">
            <?= $bodyHtml ?>
        </div>
    </div>
</section>
