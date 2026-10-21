<?php
/**
 * Registration rules overview (Figma node 2631-395): banner + one open writing
 * area (.rich, same as About) + two buttons to the guideline pages.
 *
 * @var string $heading
 * @var string $bodyHtml  trusted HTML (admin-editor output, sanitised on save)
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, 'নিয়মাবলী']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--open-page">
    <div class="container">
        <div class="rich">
            <?= $bodyHtml ?>
        </div>
        <div class="reg-actions">
            <a class="btn btn--teal-soft" href="/registration/professional"><?= e(content('reg.rules.pro')) ?></a>
            <a class="btn btn--teal-soft" href="/registration/agent"><?= e(content('reg.rules.agent')) ?></a>
        </div>
    </div>
</section>
