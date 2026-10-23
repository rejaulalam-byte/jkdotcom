<?php
/**
 * আইনি লেখার পেইজ (গোপনীয়তা নীতিমালা /privacy, ব্যবহারের শর্তাবলি /terms): ব্যানার + একটাই খোলা লেখার জায়গা (.rich)।
 *
 * @var string $heading   ব্যানারের শিরোনাম
 * @var string $crumb     ব্রেডক্রাম্বের শেষ নাম
 * @var string $bodyHtml  বিশ্বস্ত HTML (অ্যাডমিন-এডিটরের লেখা, সংরক্ষণের সময় sanitise করা)
 */
$crumbs = [['/', 'হোম'], [null, $crumb]];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--open-page">
    <div class="container rich">
        <?= $bodyHtml ?>
    </div>
</section>
