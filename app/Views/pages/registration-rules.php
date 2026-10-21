<?php
/**
 * রেজিস্ট্রেশন নিয়মাবলীর ওভারভিউ (Figma node 2631-395): ব্যানার + তিনটি নিয়মাবলী-বাটন +
 * একটা খোলা লেখার জায়গা (.rich, About-এর মতো)।
 *
 * @var string $heading
 * @var string $bodyHtml  বিশ্বস্ত HTML (অ্যাডমিন-এডিটরের লেখা, সংরক্ষণের সময় sanitise করা)
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
