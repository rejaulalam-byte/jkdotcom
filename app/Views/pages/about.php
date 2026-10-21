<?php
/**
 * আমাদের সম্পর্কে পেইজ (Figma node 2621-103): শিরোনাম-ব্যানার + একটাই খোলা লেখার জায়গা।
 * পুরো বিষয়বস্তু একটাই rich HTML (শিরোনাম, লেখা, লেখা-ঘেরা ছবি), যাতে অ্যাডমিনের
 * এডিটরে খুশিমতো সাজানো যায়।
 *
 * @var string $heading
 * @var string $bodyHtml  বিশ্বস্ত HTML (অ্যাডমিন-এডিটরের লেখা, সংরক্ষণের সময় sanitise করা)
 */
$crumbs = [['/', 'হোম'], [null, 'আমাদের সম্পর্কে']];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--open-page">
    <div class="container rich">
        <?= $bodyHtml ?>
    </div>
</section>
