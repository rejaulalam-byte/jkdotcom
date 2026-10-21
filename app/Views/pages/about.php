<?php
/**
 * About page (Figma node 2621-103): title banner + ONE open writing area.
 * The whole body is a single piece of rich HTML (headings, text, images that
 * text wraps around) so the admin's editor can lay it out freely.
 *
 * @var string $heading
 * @var string $bodyHtml  trusted HTML (admin-editor output, sanitised on save)
 */
$crumbs = [['/', 'হোম'], [null, 'আমাদের সম্পর্কে']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--open-page">
    <div class="container rich">
        <?= $bodyHtml ?>
    </div>
</section>
