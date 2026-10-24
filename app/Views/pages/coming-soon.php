<?php
/**
 * @var string $heading
 * @var string $notice  মূল লেখা (শিরোনামের নিচে)
 * @var string $text    ছোট ব্যাখ্যা
 * @var list<array{0:?string,1:string}>|null $crumbs
 */
$crumbs ??= [['/', 'হোম'], [null, $heading]];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--plain">
    <div class="container center">
        <h2 class="section__title"><?= e($notice) ?></h2>
        <p class="section__text"><?= e($text) ?></p>
        <p><a class="btn btn--teal-soft" href="/">হোম পেজে ফিরে যান</a></p>
    </div>
</section>
