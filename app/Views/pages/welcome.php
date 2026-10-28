<?php
/**
 * লগইনের পরের স্বাগত পেইজ (Figma node 2663-981 পেশাজীবী, 2686-740 প্রতিনিধি)।
 * সদস্যের নাম সোনালি ফোঁটা-দাগের জায়গায় বসে।
 *
 * @var string $heading
 * @var string $crumb
 * @var string $role   'worker' | 'agent'
 * @var string $name
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, $crumb]];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--welcome">
    <div class="container">
        <p class="welcome__line"><span class="welcome__name"><?= e($name) ?>,</span> <?= e(content('welcome.' . $role . '.line_1')) ?></p>
        <p class="welcome__line"><?= t('welcome.line_2') ?></p>
    </div>
</section>
