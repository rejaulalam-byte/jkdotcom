<?php
/**
 * লগইনের পরের স্বাগত পেইজ (Figma node 2663-981 পেশাজীবী, 2686-740 প্রতিনিধি)।
 * সদস্যের নাম সোনালি ফোঁটা-দাগের জায়গায় বসে।
 *
 * @var string $heading
 * @var string $crumb
 * @var string $role   'worker' | 'agent'
 * @var string $name
 * @var string $token  লগআউট ফর্মের CSRF টোকেন
 */
$crumbs = [['/', 'হোম'], ['/registration', 'রেজিস্ট্রেশন'], [null, $crumb]];
require BASE_PATH . '/app/Views/partials/page-banner.php';
?>
<section class="section section--welcome">
    <div class="container">
        <p class="welcome__line"><span class="welcome__name"><?= e($name) ?>,</span> <?= e(content('welcome.' . $role . '.line_1')) ?></p>
        <p class="welcome__line"><?= e(content('welcome.line_2')) ?></p>

        <form class="welcome__out" action="/logout" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <button class="pf-submit" type="submit"><?= e(content('welcome.logout')) ?></button>
        </form>
    </div>
</section>
