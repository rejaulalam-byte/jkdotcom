<?php
/**
 * পেশাজীবীদের রেজিস্ট্রেশন-নির্দেশিকা (Figma node 2683-148): ব্যানার, তিনটি নিয়মাবলী-বাটন,
 * একটা খোলা লেখার জায়গা (.rich), তারপর দুটো ছবির কার্ডসহ হালকা ব্যান্ড।
 *
 * @var string $heading
 * @var string $bodyHtml  বিশ্বস্ত HTML (অ্যাডমিন-এডিটরের লেখা, সংরক্ষণের সময় sanitise করা)
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
