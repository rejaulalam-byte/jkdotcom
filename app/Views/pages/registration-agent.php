<?php
/**
 * Registration guideline for agents/representatives (Figma node 2683-305): same layout
 * as the professional guideline (three rules buttons, text, band with two photo cards).
 *
 * @var string $heading
 * @var string $bodyHtml  trusted HTML (admin-editor output, sanitised on save)
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, 'প্রতিনিধি রেজিস্ট্রেশনের নিয়মাবলী']];
$regActive = 'agent';
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
