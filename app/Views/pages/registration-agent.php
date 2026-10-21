<?php
/**
 * এজেন্ট/প্রতিনিধির রেজিস্ট্রেশন-নির্দেশিকা (Figma node 2683-305): পেশাজীবীদের নির্দেশিকার
 * মতোই বিন্যাস (তিনটি নিয়মাবলী-বাটন, লেখা, দুটো ছবির কার্ডসহ ব্যান্ড)।
 *
 * @var string $heading
 * @var string $bodyHtml  বিশ্বস্ত HTML (অ্যাডমিন-এডিটরের লেখা, সংরক্ষণের সময় sanitise করা)
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
